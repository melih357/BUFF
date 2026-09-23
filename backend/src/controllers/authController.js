const bcrypt = require('bcryptjs');
const pool = require('../config/db');
const { createSharedSession, readToken, revokeSharedSession } = require('../v14/session');

function publicUser(user) {
  return { id:Number(user.id), username:user.username, email:user.email, displayName:user.display_name, avatarUrl:user.avatar_url, isAdmin:Boolean(user.is_admin), accountStatus:user.account_status, createdAt:user.created_at };
}
function setSessionCookie(res, session) {
  const secure = String(process.env.COOKIE_SECURE || '').toLowerCase() === 'true' || process.env.COOKIE_SECURE === '1';
  const maxAge = Math.max(900, Number(process.env.JWT_TTL_SECONDS || 604800));
  res.setHeader('Set-Cookie', `buff_token=${encodeURIComponent(session.token)}; Path=/; HttpOnly; SameSite=Lax; Max-Age=${maxAge}${secure ? '; Secure' : ''}`);
}
async function register(req,res){
  try{
    const {username,email,password,displayName}=req.body||{};
    if(!username||!email||!password)return res.status(400).json({message:'Kullanıcı adı, e-posta ve şifre zorunludur.'});
    const cleanUsername=String(username).trim(), cleanEmail=String(email).trim().toLowerCase(), cleanDisplayName=String(displayName||cleanUsername).trim();
    if(!/^[a-zA-Z0-9_]{3,24}$/.test(cleanUsername))return res.status(400).json({message:'Kullanıcı adı 3-24 karakter olmalı; sadece harf, rakam ve alt çizgi kullanılabilir.'});
    if(!/^\S+@\S+\.\S+$/.test(cleanEmail))return res.status(400).json({message:'Geçerli bir e-posta adresi girin.'});
    if(String(password).length<6)return res.status(400).json({message:'Şifre en az 6 karakter olmalıdır.'});
    const [existing]=await pool.query('SELECT id FROM users WHERE username=? OR email=? LIMIT 1',[cleanUsername,cleanEmail]);
    if(existing.length)return res.status(409).json({message:'Bu kullanıcı adı veya e-posta zaten kullanımda.'});
    const passwordHash=await bcrypt.hash(String(password),12);
    const [result]=await pool.query("INSERT INTO users (username,email,password_hash,display_name,account_status) VALUES (?,?,?,?,'active')",[cleanUsername,cleanEmail,passwordHash,cleanDisplayName]);
    await pool.query('INSERT IGNORE INTO user_features (user_id) VALUES (?)',[result.insertId]);
    await pool.query('INSERT IGNORE INTO user_preferences (user_id) VALUES (?)',[result.insertId]);
    const [[user]]=await pool.query('SELECT * FROM users WHERE id=?',[result.insertId]);
    const session=await createSharedSession(user,req); setSessionCookie(res,session);
    return res.status(201).json({message:'Hesap oluşturuldu.',token:session.token,accessToken:session.token,expiresAt:session.expiresAt,user:publicUser(user)});
  }catch(error){console.error('Register error:',error);return res.status(500).json({message:'Kayıt sırasında sunucu hatası oluştu.'});}
}
async function login(req,res){
  try{
    const {login,password}=req.body||{}; if(!login||!password)return res.status(400).json({message:'Kullanıcı adı/e-posta ve şifre zorunludur.'});
    const normalized=String(login).trim(); const [rows]=await pool.query('SELECT * FROM users WHERE username=? OR email=? LIMIT 1',[normalized,normalized.toLowerCase()]);
    if(!rows.length)return res.status(401).json({message:'Kullanıcı bilgileri hatalı.'});
    const user=rows[0]; if(String(user.account_status||'active')!=='active')return res.status(403).json({message:'Bu hesap aktif değil.'});
    if(!await bcrypt.compare(String(password),user.password_hash))return res.status(401).json({message:'Kullanıcı bilgileri hatalı.'});
    const session=await createSharedSession(user,req); setSessionCookie(res,session);
    return res.json({message:'Giriş başarılı.',token:session.token,accessToken:session.token,expiresAt:session.expiresAt,user:publicUser(user)});
  }catch(error){console.error('Login error:',error);return res.status(500).json({message:'Giriş sırasında sunucu hatası oluştu.'});}
}
async function logout(req,res){
  try{await revokeSharedSession(readToken(req));}catch{}
  res.setHeader('Set-Cookie','buff_token=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0'); return res.json({message:'Çıkış yapıldı.'});
}
async function me(req,res){
  try{const [rows]=await pool.query('SELECT * FROM users WHERE id=? LIMIT 1',[req.user.id]);if(!rows.length)return res.status(404).json({message:'Kullanıcı bulunamadı.'});return res.json({user:publicUser(rows[0])});}
  catch(error){console.error('Me error:',error);return res.status(500).json({message:'Kullanıcı bilgileri alınamadı.'});}
}
module.exports={register,login,logout,me};
