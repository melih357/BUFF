const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const mustExist = [
  'backend/v14.php','backend/server.js','backend/worker.js','backend/api/metrics.php','backend/api/health.php','backend/api/realtime/poll.php',
  'frontend/assets/js/v14.js','frontend/assets/css/v14.css','database/BUFF_DATABASE_SETUP.sql','.env.example','.env.production.example',
  'docker-compose.infrastructure.yml','docs/ARCHITECTURE_V14.md'
];
const tables = ['shared_sessions','job_queue','media_variants','realtime_events','finance_audit_logs','moderation_queue','user_loyalty','channel_loyalty','predictions','prediction_options','prediction_entries','community_goals','content_interactions','clip_gifts','api_telemetry'];
let failed = false;
for (const rel of mustExist) { if (!fs.existsSync(path.join(root,rel))) { console.error(`missing file: ${rel}`); failed=true; } }
const sql=fs.readFileSync(path.join(root,'database/BUFF_DATABASE_SETUP.sql'),'utf8');
for(const t of tables){ if(!sql.includes('CREATE TABLE IF NOT EXISTS `'+t+'`')){console.error(`missing table: ${t}`);failed=true;} }
for(const token of ['14.3.0','hls_url','dash_url','membership_tier_required','metadata_json']){if(!sql.includes(token)){console.error(`schema marker missing: ${token}`);failed=true;}}
if(failed)process.exit(1);
console.log(`V14 static structure OK (${mustExist.length} files, ${tables.length} core V14 tables).`);
