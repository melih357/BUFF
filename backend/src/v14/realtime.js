const { WebSocketServer } = require('ws');
const { validateToken } = require('./session');
const { canStream } = require('./privacy');
const { getRedis } = require('./redis');

function parseCookie(cookie = '') {
  const m = cookie.match(/(?:^|;\s*)buff_token=([^;]+)/); return m ? decodeURIComponent(m[1]) : null;
}
function attachRealtime(server) {
  const wss = new WebSocketServer({ server, path: '/realtime' });
  const clients = new Set();
  wss.on('connection', async (ws, req) => {
    try {
      const u = new URL(req.url, 'http://localhost');
      const token = u.searchParams.get('token') || parseCookie(req.headers.cookie || '');
      const payload = await validateToken(token);
      if (!payload) { ws.close(4001, 'Unauthorized'); return; }
      ws.buffUserId = Number(payload.sub); ws.buffTopics = new Set([`user.${ws.buffUserId}`, 'global', 'feed']); clients.add(ws);
      ws.send(JSON.stringify({ event: 'realtime.ready', userId: ws.buffUserId }));
      ws.on('message', raw => {
        try { const msg = JSON.parse(String(raw)); if (msg.type === 'subscribe' && typeof msg.topic === 'string' && msg.topic.length < 120) ws.buffTopics.add(msg.topic); if (msg.type === 'unsubscribe') ws.buffTopics.delete(msg.topic); } catch {}
      });
      ws.on('close', () => clients.delete(ws));
    } catch { ws.close(1011); }
  });

  (async () => {
    const redis = await getRedis(); if (!redis) return;
    const sub = redis.duplicate(); await sub.connect();
    await sub.subscribe('buff:realtime', raw => deliver(raw).catch(err=>console.error('[realtime]',err.message)));
    // Compatibility with deployments that do not use the prefix in Node.
    await sub.subscribe('realtime', raw => deliver(raw));
  })().catch(err => console.error('[BUFF realtime redis]', err.message));

  async function deliver(raw) {
    let msg; try { msg = typeof raw === 'string' ? JSON.parse(raw) : raw; } catch { return; }
    const topic = msg.topic || 'global'; const userId = Number(msg.userId || 0); const streamId = Number(msg.streamId || 0);
    for (const ws of clients) {
      if (ws.readyState !== 1) continue;
      const permitted = userId ? ws.buffUserId === userId : ws.buffTopics.has(topic) || (streamId && ws.buffTopics.has(`stream.${streamId}`));
      if (permitted && (!streamId || await canStream(streamId,ws.buffUserId))) ws.send(JSON.stringify(msg));
    }
  }
  return { wss, clients, broadcast: deliver };
}
module.exports = { attachRealtime };
