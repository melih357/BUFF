const jwt = require('jsonwebtoken');
const pool = require('../config/db');
const { getRedis } = require('./redis');

function secret() {
  return process.env.JWT_SECRET || process.env.APP_ENCRYPTION_KEY || 'BUFF-local-development-key';
}
function readToken(req) {
  const auth = req.headers.authorization || '';
  if (auth.startsWith('Bearer ')) return auth.slice(7).trim();
  const cookie = req.headers.cookie || '';
  const match = cookie.match(/(?:^|;\s*)buff_token=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}
async function validateToken(token) {
  if (!token) return null;
  let payload;
  try { payload = jwt.verify(token, secret(), { algorithms: ['HS256'], issuer: 'buff-v14' }); }
  catch { return null; }
  const userId = Number(payload.sub || 0); const jti = String(payload.jti || '');
  if (!userId || !jti) return null;
  const tokenHash = require('crypto').createHash('sha256').update(token).digest('hex');
  const redis = await getRedis();
  if (redis) {
    try {
      const raw = await redis.get(`buff:session:${jti}`) || await redis.get(`session:${jti}`);
      if (raw) {
        const session = JSON.parse(raw);
        if (Number(session.userId) === userId && session.tokenHash === tokenHash) return payload;
      }
    } catch {}
  }
  const [rows] = await pool.query('SELECT user_id, token_hash FROM shared_sessions WHERE jti=? AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1', [jti]);
  if (!rows.length || Number(rows[0].user_id) !== userId || rows[0].token_hash !== tokenHash) return null;
  return payload;
}

async function createSharedSession(user, req) {
  const crypto = require('crypto');
  const jti = crypto.randomBytes(20).toString('hex');
  const ttl = Math.max(900, Number(process.env.JWT_TTL_SECONDS || 604800));
  const payload = { sub: String(user.id), jti, adm: Number(user.is_admin || 0) ? 1 : 0 };
  const token = jwt.sign(payload, secret(), { algorithm: 'HS256', issuer: 'buff-v14', expiresIn: ttl });
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  const expiresAt = new Date(Date.now() + ttl * 1000);
  await pool.query(
    'INSERT INTO shared_sessions (user_id,jti,token_hash,expires_at,ip_address,user_agent) VALUES (?,?,?,?,?,?)',
    [Number(user.id), jti, tokenHash, expiresAt, String(req?.ip || req?.socket?.remoteAddress || '').slice(0,64), String(req?.headers?.['user-agent'] || '').slice(0,255)]
  );
  const redis = await getRedis();
  if (redis) {
    try { await redis.set(`buff:session:${jti}`, JSON.stringify({ userId: Number(user.id), admin: Boolean(user.is_admin), tokenHash }), { EX: ttl }); } catch {}
  }
  return { token, expiresAt: expiresAt.toISOString(), jti };
}
async function revokeSharedSession(token) {
  if (!token) return;
  let payload; try { payload = jwt.verify(token, secret(), { algorithms: ['HS256'], issuer: 'buff-v14' }); } catch { return; }
  const jti = String(payload.jti || ''); if (!jti) return;
  try { await pool.query('UPDATE shared_sessions SET revoked_at=NOW() WHERE jti=?', [jti]); } catch {}
  const redis = await getRedis(); if (redis) { try { await redis.del(`buff:session:${jti}`); } catch {} }
}
async function requireSharedAuth(req, res, next) {
  try {
    const payload = await validateToken(readToken(req));
    if (!payload) return res.status(401).json({ message: 'Oturum açmanız gerekiyor.' });
    req.user = { id: Number(payload.sub), isAdmin: Boolean(payload.adm), tokenPayload: payload };
    next();
  } catch (err) { next(err); }
}
module.exports = { readToken, validateToken, createSharedSession, revokeSharedSession, requireSharedAuth };
