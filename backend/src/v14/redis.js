const { createClient } = require('redis');

let clientPromise = null;
function enabled() {
  return String(process.env.REDIS_ENABLED || '').toLowerCase() === 'true' || process.env.REDIS_ENABLED === '1';
}
async function getRedis() {
  if (!enabled()) return null;
  if (clientPromise) return clientPromise;
  clientPromise = (async () => {
    const url = process.env.REDIS_URL || `redis://${process.env.REDIS_HOST || '127.0.0.1'}:${process.env.REDIS_PORT || 6379}`;
    const client = createClient({
      url,
      password: process.env.REDIS_PASSWORD || undefined,
      database: Number(process.env.REDIS_DB || 0),
      socket: { reconnectStrategy: retries => Math.min(retries * 100, 3000) },
    });
    client.on('error', err => console.error('[BUFF Redis]', err.message));
    await client.connect();
    return client;
  })().catch(err => { clientPromise = null; console.error('[BUFF Redis connect]', err.message); return null; });
  return clientPromise;
}
module.exports = { getRedis, enabled };
