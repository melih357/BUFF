const path = require('path');
const fs = require('fs');
const os = require('os');
const { spawn } = require('child_process');
const dotenv = require('dotenv');
dotenv.config({ path: path.join(__dirname, '..', '.env') });
const pool = require('./src/config/db');
const storage = require('./src/v14/storage');
const { jobsProcessed } = require('./src/v14/metrics');

const WORKER = `worker-${process.pid}-${Math.random().toString(16).slice(2,8)}`;
const SLEEP = Number(process.env.WORKER_POLL_MS || 1200);
const FFMPEG = process.env.FFMPEG_PATH || 'ffmpeg';
let stopping = false;
process.on('SIGINT', () => stopping = true); process.on('SIGTERM', () => stopping = true);
const wait = ms => new Promise(r => setTimeout(r, ms));

async function claimJob() {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [rows] = await conn.query("SELECT * FROM job_queue WHERE status='pending' AND available_at<=NOW() ORDER BY id LIMIT 1 FOR UPDATE");
    if (!rows.length) { await conn.commit(); return null; }
    const job = rows[0];
    await conn.query("UPDATE job_queue SET status='processing',locked_at=NOW(),locked_by=?,attempts=attempts+1 WHERE id=?", [WORKER, job.id]);
    await conn.commit(); return job;
  } catch (e) { await conn.rollback(); throw e; }
  finally { conn.release(); }
}
async function finish(job, ok, error = null) {
  const status = ok ? 'done' : (Number(job.attempts || 0) + 1 >= Number(process.env.JOB_MAX_ATTEMPTS || 4) ? 'failed' : 'pending');
  const delay = ok ? 0 : Math.min(300, 10 * Math.pow(2, Number(job.attempts || 0)));
  await pool.query("UPDATE job_queue SET status=?,locked_at=NULL,locked_by=NULL,last_error=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id=?", [status, error ? String(error).slice(0,2000) : null, delay, job.id]);
  jobsProcessed.inc({ type: job.job_type, status: ok ? 'ok' : status });
}
function run(bin, args, opts = {}) {
  return new Promise((resolve, reject) => {
    const p = spawn(bin, args, { stdio: ['ignore','ignore','pipe'], ...opts }); let err = '';
    p.stderr.on('data', d => { err += String(d); if (err.length > 20000) err = err.slice(-20000); });
    p.on('error', reject); p.on('close', code => code === 0 ? resolve() : reject(new Error(`${bin} exited ${code}: ${err.slice(-4000)}`)));
  });
}
async function materializeInput(payload, dir) {
  const ext = (payload.inputUrl || '').includes('.mp4') ? '.mp4' : '.webm'; const target = path.join(dir, `input${ext}`);
  if (payload.storageKey) { await storage.getToFile(payload.storageKey, target); return target; }
  const url = String(payload.inputUrl || '');
  if (/^https?:\/\//i.test(url)) {
    const res = await fetch(url); if (!res.ok) throw new Error(`Input download HTTP ${res.status}`); const ab = await res.arrayBuffer(); await fs.promises.writeFile(target, Buffer.from(ab)); return target;
  }
  const local = path.resolve(__dirname, '..', url.replace(/^\.\.\//, '')); await fs.promises.copyFile(local, target); return target;
}
async function uploadTree(root, prefix) {
  const outputs = [];
  async function walk(dir) {
    for (const ent of await fs.promises.readdir(dir, { withFileTypes: true })) {
      const abs = path.join(dir, ent.name); if (ent.isDirectory()) { await walk(abs); continue; }
      const rel = path.relative(root, abs).split(path.sep).join('/');
      const ext = path.extname(abs).toLowerCase(); const ct = ext === '.m3u8' ? 'application/vnd.apple.mpegurl' : ext === '.ts' ? 'video/mp2t' : ext === '.mpd' ? 'application/dash+xml' : ext === '.m4s' ? 'video/iso.segment' : 'application/octet-stream';
      const item = await storage.putFile(abs, `${prefix}/${rel}`, ct); outputs.push({ rel, ...item });
    }
  }
  await walk(root); return outputs;
}
async function transcode(job, payload) {
  const ownerType = String(payload.ownerType || 'recording'); const ownerId = Number(payload.ownerId || 0); if (!ownerId) throw new Error('ownerId missing');
  const temp = await fs.promises.mkdtemp(path.join(os.tmpdir(), `buff-v14-${ownerType}-${ownerId}-`));
  try {
    const input = await materializeInput(payload, temp); const out = path.join(temp, 'out'); await fs.promises.mkdir(out, { recursive: true });
    const playback=path.join(out,'playback.mp4');await run(FFMPEG,['-y','-i',input,'-map','0:v:0','-map','0:a:0?','-vf','scale=w=1280:h=720:force_original_aspect_ratio=decrease:force_divisible_by=2','-c:v','libx264','-preset','veryfast','-pix_fmt','yuv420p','-c:a','aac','-movflags','+faststart',playback]);const playbackKey=`variants/${ownerType}/${ownerId}/playback.mp4`;await storage.putFile(playback,playbackKey,'video/mp4');const playbackTable={clip:'clips',recording:'recordings',feed:'feed_posts'}[ownerType];if(playbackTable)await pool.query(`UPDATE ${playbackTable} SET playback_key=? WHERE id=?`,[playbackKey,ownerId]);
    const profiles = [
      { name:'360p', w:640, h:360, br:'800k', max:'856k', buf:'1200k' },
      { name:'720p', w:1280, h:720, br:'2500k', max:'2675k', buf:'3750k' },
      { name:'1080p', w:1920, h:1080, br:'5000k', max:'5350k', buf:'7500k' },
    ];
    for (const p of profiles) {
      const d = path.join(out,p.name); await fs.promises.mkdir(d,{recursive:true});
      await run(FFMPEG,['-y','-i',input,'-vf',`scale=w=${p.w}:h=${p.h}:force_original_aspect_ratio=decrease`,'-c:v','libx264','-preset',process.env.FFMPEG_PRESET||'veryfast','-profile:v','main','-g','48','-keyint_min','48','-sc_threshold','0','-b:v',p.br,'-maxrate',p.max,'-bufsize',p.buf,'-c:a','aac','-b:a','128k','-ac','2','-ar','48000','-f','hls','-hls_time','4','-hls_playlist_type','vod','-hls_segment_filename',path.join(d,'seg_%05d.ts'),path.join(d,'index.m3u8')]);
    }
    const master = ['#EXTM3U','#EXT-X-VERSION:3','#EXT-X-STREAM-INF:BANDWIDTH=950000,RESOLUTION=640x360','360p/index.m3u8','#EXT-X-STREAM-INF:BANDWIDTH=2800000,RESOLUTION=1280x720','720p/index.m3u8','#EXT-X-STREAM-INF:BANDWIDTH=5500000,RESOLUTION=1920x1080','1080p/index.m3u8',''].join('\n');
    await fs.promises.writeFile(path.join(out,'master.m3u8'),master);
    const dashDir = path.join(out,'dash'); await fs.promises.mkdir(dashDir,{recursive:true});
    await run(FFMPEG,['-y','-i',input,'-map','0:v:0','-map','0:a:0?','-c:v','libx264','-preset',process.env.FFMPEG_PRESET||'veryfast','-b:v','2500k','-vf','scale=w=1280:h=720:force_original_aspect_ratio=decrease','-c:a','aac','-b:a','128k','-f','dash','-seg_duration','4','-use_template','1','-use_timeline','1',path.join(dashDir,'stream.mpd')]);
    const prefix = `variants/${ownerType}/${ownerId}`; const uploaded = await uploadTree(out,prefix);
    const findUrl = rel => uploaded.find(x => x.rel === rel)?.url || null; const masterUrl = findUrl('master.m3u8'); const dashUrl = findUrl('dash/stream.mpd');
    const conn = await pool.getConnection(); try {
      await conn.beginTransaction();
      for (const p of profiles) {
        const u = findUrl(`${p.name}/index.m3u8`); if (!u) continue;
        await conn.query("INSERT INTO media_variants (owner_type,owner_id,quality,protocol,file_url,storage_key,bitrate_kbps,width,height,status) VALUES (?,?,?,?,?,?,?,?,?,'ready') ON DUPLICATE KEY UPDATE file_url=VALUES(file_url),storage_key=VALUES(storage_key),bitrate_kbps=VALUES(bitrate_kbps),width=VALUES(width),height=VALUES(height),status='ready'",[ownerType,ownerId,p.name,'hls',u,`${prefix}/${p.name}/index.m3u8`,parseInt(p.br),p.w,p.h]);
      }
      if (dashUrl) await conn.query("INSERT INTO media_variants (owner_type,owner_id,quality,protocol,file_url,storage_key,bitrate_kbps,width,height,status) VALUES (?,?,?,?,?,?,?,?,?,'ready') ON DUPLICATE KEY UPDATE file_url=VALUES(file_url),storage_key=VALUES(storage_key),status='ready'",[ownerType,ownerId,'720p','dash',dashUrl,`${prefix}/dash/stream.mpd`,2500,1280,720]);
      const table = ownerType === 'clip' ? 'clips' : ownerType === 'recording' ? 'recordings' : null;
      if (table) await conn.query(`UPDATE ${table} SET hls_url=?,dash_url=?,processing_status='ready',status='ready' WHERE id=?`,[masterUrl,dashUrl,ownerId]).catch(async()=>{ await conn.query(`UPDATE ${table} SET hls_url=?,dash_url=?,processing_status='ready' WHERE id=?`,[masterUrl,dashUrl,ownerId]); });
      await conn.commit();
    } catch(e){ await conn.rollback(); throw e; } finally { conn.release(); }
  } finally { await fs.promises.rm(temp,{recursive:true,force:true}); }
}
async function moderation(payload) {
  const id = Number(payload.moderationId || 0); if (!id) return;
  const url = process.env.AI_MODERATION_WEBHOOK || '';
  let score = 0, labels = [], status = 'approved';
  if (url) {
    const res = await fetch(url,{method:'POST',headers:{'content-type':'application/json','authorization':process.env.AI_MODERATION_API_KEY?`Bearer ${process.env.AI_MODERATION_API_KEY}`:''},body:JSON.stringify(payload)});
    if (!res.ok) throw new Error(`Moderation webhook HTTP ${res.status}`); const data = await res.json(); score = Number(data.score || 0); labels = data.labels || []; status = data.allow === false ? 'flagged' : 'approved';
  }
  await pool.query('UPDATE moderation_queue SET status=?,risk_score=?,labels_json=?,reviewed_at=NOW() WHERE id=?',[status,score,JSON.stringify(labels),id]);
}
async function sendEmail(payload) {
  const hook = process.env.EMAIL_WEBHOOK_URL || ''; if (!hook) { console.log('[BUFF email fallback]', payload.to, payload.subject); return; }
  const res = await fetch(hook,{method:'POST',headers:{'content-type':'application/json','authorization':process.env.EMAIL_WEBHOOK_TOKEN?`Bearer ${process.env.EMAIL_WEBHOOK_TOKEN}`:''},body:JSON.stringify(payload)}); if(!res.ok)throw new Error(`Email webhook HTTP ${res.status}`);
}
async function broadcastNotification(payload) {
  const scope = String(payload.targetScope || 'all');
  let sql = 'SELECT u.id FROM users u'; const args = [];
  if (scope === 'creators') sql += " WHERE u.creator_enabled=1 OR u.account_mode='creator'";
  else if (scope === 'vip') sql += " JOIN user_features uf ON uf.user_id=u.id WHERE uf.vip_until IS NOT NULL AND uf.vip_until>NOW()";
  sql += ' ORDER BY u.id ASC LIMIT 100000';
  const [users] = await pool.query(sql, args);
  if (!users.length) return;
  const actor = Number(payload.actorUserId || 0) || null;
  const title = String(payload.title || 'BUFF Duyuru').slice(0,140);
  const body = String(payload.body || '').slice(0,255);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const batchSize = 500;
    for (let i=0;i<users.length;i+=batchSize) {
      const batch=users.slice(i,i+batchSize); const placeholders=batch.map(()=>'(?,?,?,?,?,?,NOW())').join(','); const values=[];
      for (const u of batch) values.push(Number(u.id),actor,'announcement',title,body,'./index.php');
      await conn.query(`INSERT INTO notifications (user_id,actor_user_id,type,title,body,link_url,created_at) VALUES ${placeholders}`, values);
    }
    await conn.commit();
  } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
}
async function processJob(job) {
  const payload = JSON.parse(job.payload_json || '{}');
  if (job.job_type === 'media_transcode') return transcode(job,payload);
  if (job.job_type === 'moderate_media' || job.job_type === 'moderate_text') return moderation(payload);
  if (job.job_type === 'send_email') return sendEmail(payload);
  if (job.job_type === 'storage_cleanup') { const key = payload.storageKey || storage.keyFromUrl(payload.url); if (key) await storage.deleteKey(key); return; }
  if (job.job_type === 'broadcast_notification') return broadcastNotification(payload);
  console.log('[BUFF worker] unknown job type', job.job_type);
}
async function main() {
  console.log(`[BUFF V14 worker] started as ${WORKER}`);
  while (!stopping) {
    let job; try { job = await claimJob(); } catch(e){ console.error('[worker claim]',e.message); await wait(SLEEP); continue; }
    if (!job) { await wait(SLEEP); continue; }
    try { await processJob(job); await finish(job,true); }
    catch(e){ console.error(`[worker ${job.id} ${job.job_type}]`,e.message); await finish(job,false,e.message); }
  }
  await pool.end(); console.log('[BUFF V14 worker] stopped');
}
main().catch(e=>{console.error(e);process.exit(1);});
