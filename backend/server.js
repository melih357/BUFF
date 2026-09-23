const path = require('path');
const http = require('http');
const express = require('express');
const helmet = require('helmet');
const dotenv = require('dotenv');
dotenv.config({ path: path.join(__dirname, '..', '.env') });

const pool = require('./src/config/db');
const authRoutes = require('./src/routes/authRoutes');
const streamRoutes = require('./src/routes/streamRoutes');
const { attachRealtime } = require('./src/v14/realtime');
const metrics = require('./src/v14/metrics');
const { getRedis } = require('./src/v14/redis');

const app = express(); const PORT = Number(process.env.PORT || 3000);
app.disable('x-powered-by');
app.use(helmet({ contentSecurityPolicy: false, crossOriginResourcePolicy: { policy: 'cross-origin' } }));
app.use(express.json({ limit: '2mb' })); app.use(express.urlencoded({ extended: true })); app.use(metrics.middleware);

app.get('/api/health', async (req,res) => {
  const state = { ok: true, database: false, redis: false, realtime: true, version: '17.0.0' };
  try { await pool.query('SELECT 1'); state.database = true; } catch (e) { state.ok = false; state.databaseError = e.message; }
  try { const r = await getRedis(); if (r) { await r.ping(); state.redis = true; } else state.redis = 'fallback-mysql'; } catch { state.redis = false; }
  res.status(state.ok ? 200 : 503).json(state);
});
app.get('/metrics', async (req,res) => { res.set('Content-Type', metrics.client.register.contentType); res.end(await metrics.client.register.metrics()); });
app.use('/api/auth', authRoutes); app.use('/api/streams', streamRoutes);
app.use('/uploads/:folder',(req,res,next)=>['clips','recordings','feed','thumbnails','variants','.chunks'].includes(req.params.folder)?res.sendStatus(403):next());
app.use('/uploads', express.static(path.join(__dirname, '..', 'uploads'), { maxAge: '1h' }));
app.use(express.static(path.join(__dirname, '..', 'frontend')));
app.get('*', (req,res) => res.sendFile(path.join(__dirname, '..', 'frontend', 'index.html')));

const server = http.createServer(app); const realtime = attachRealtime(server);
setInterval(() => metrics.realtimeConnections.set(realtime.clients.size), 5000).unref();
server.listen(PORT, () => console.log(`BUFF V17 realtime/API node: http://localhost:${PORT}`));
