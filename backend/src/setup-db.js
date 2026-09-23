const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const dotenv = require('dotenv');
const bcrypt = require('bcryptjs');

dotenv.config({ path: path.join(__dirname, '..', '..', '.env') });

const projectRoot = path.join(__dirname, '..', '..');
const schemaFile = path.join(projectRoot, 'database', 'BUFF_DATABASE_SETUP.sql');
const dbName = String(process.env.DB_DATABASE || 'buff_v126').replace(/[^a-zA-Z0-9_]/g, '') || 'buff_v126';

const requiredTables = [
  'users','streams','stream_viewers','gift_catalog','gift_events','wallet_transactions','signal_messages','follows','chat_messages','notifications',
  'user_features','bank_accounts','stream_payout_preferences','stream_moderators','chat_restrictions','stream_chat_settings','chat_message_meta','reports',
  'gift_settlements','gift_refund_requests','bank_payment_requests','feed_posts','feed_post_likes','user_preferences','payout_requests','support_tickets',
  'username_change_history','feed_post_comments','feed_post_shares','payment_orders','channel_profiles','recordings','clips','categories','user_sessions',
  'saved_posts','admin_audit_logs','site_settings','payment_methods','buff_install_state','admin_roles','admin_role_assignments',
  'cms_titles','cms_assets','cms_seasons','cms_episodes','cms_collections','cms_collection_items','membership_plans','stream_keys','ip_blocks',
  'system_announcements','epg_events','transcode_jobs','drm_policies','shared_sessions','job_queue','media_variants','realtime_events',
  'finance_audit_logs','moderation_queue','user_loyalty','channel_loyalty','predictions','prediction_options','prediction_entries','community_goals','channel_schedule',
  'content_interactions','clip_gifts','api_telemetry','mutation_receipts','follow_requests','stream_metric_samples'
];

const requiredColumns = [
  ['users','is_private'],['users','replay_visibility'],['recordings','playback_key'],['clips','playback_key'],['feed_posts','playback_key'],['gift_events','recording_id'],['users','account_mode'],['users','creator_enabled'],['users','creator_earnings'],['users','is_admin'],['users','account_status'],['users','phone'],['users','billing_address'],
  ['streams','delay_seconds'],['streams','adult_only'],['streams','mature_language'],['streams','ended_at'],
  ['reports','report_scope'],['reports','chat_message_id'],['reports','feed_post_id'],
  ['user_preferences','theme_mode'],['user_preferences','accent_theme'],['user_features','vip_tier'],
  ['feed_posts','video_url'],['feed_posts','poll_json'],['feed_posts','is_pinned'],['feed_posts','visibility'],['feed_posts','membership_tier_required'],['feed_posts','clip_id'],
  ['payment_orders','order_type'],['payment_orders','order_meta_json'],
  ['recordings','hls_url'],['recordings','dash_url'],['recordings','processing_status'],['recordings','storage_key'],['recordings','storage_provider'],
  ['clips','hls_url'],['clips','dash_url'],['clips','processing_status'],['clips','storage_key'],['clips','storage_provider'],['notifications','metadata_json']
];

async function count(conn, sql, params = []) {
  const [rows] = await conn.execute(sql, params);
  return Number(rows?.[0]?.c || 0);
}

async function validate(conn) {
  const missingTables = [];
  for (const table of requiredTables) {
    const c = await count(conn,
      'SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
      [dbName, table]);
    if (!c) missingTables.push(table);
  }
  const missingColumns = [];
  for (const [table, column] of requiredColumns) {
    const c = await count(conn,
      'SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
      [dbName, table, column]);
    if (!c) missingColumns.push(`${table}.${column}`);
  }
  if (missingTables.length || missingColumns.length) {
    throw new Error(`Veritabanı doğrulaması başarısız. Eksik tablolar: ${missingTables.join(', ') || '-'} | Eksik alanlar: ${missingColumns.join(', ') || '-'}`);
  }
}

async function seedAdmin(conn) {
  const username = String(process.env.ADMIN_USERNAME || 'admin').trim();
  const email = String(process.env.ADMIN_EMAIL || 'admin@buff.local').trim().toLowerCase();
  const password = String(process.env.ADMIN_PASSWORD || 'change-this-password');
  if (!username || !email || !password) return;
  const [rows] = await conn.execute('SELECT id FROM users WHERE username=? OR email=? LIMIT 1', [username, email]);
  let adminId;
  if (rows.length) {
    adminId = Number(rows[0].id);
    await conn.execute("UPDATE users SET is_admin=1, account_status='active' WHERE id=?", [adminId]);
  } else {
    const hash = await bcrypt.hash(password, 12);
    const [result] = await conn.execute(
      "INSERT INTO users (username,email,password_hash,display_name,is_admin,account_status) VALUES (?,?,?,?,1,'active')",
      [username, email, hash, 'BUFF Admin']
    );
    adminId = Number(result.insertId);
  }
  const [roles] = await conn.execute("SELECT id FROM admin_roles WHERE code='super_admin' LIMIT 1");
  if (roles.length) await conn.execute('INSERT IGNORE INTO admin_role_assignments (user_id,role_id) VALUES (?,?)',[adminId,Number(roles[0].id)]);
}

async function run() {
  let conn;
  try {
    if (!fs.existsSync(schemaFile)) throw new Error(`SQL dosyası bulunamadı: ${schemaFile}`);
    let sql = fs.readFileSync(schemaFile, 'utf8');
    sql = sql.replace(/`buff_v126`/g, `\`${dbName}\``);

    conn = await mysql.createConnection({
      host: process.env.DB_HOST || '127.0.0.1',
      port: Number(process.env.DB_PORT || 3306),
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || '',
      charset: 'utf8mb4',
      multipleStatements: true
    });

    console.log(`BUFF V14 veritabanı hazırlanıyor: ${dbName}`);
    await conn.query(sql);
    await conn.query(`USE \`${dbName}\``);
    await seedAdmin(conn);
    await validate(conn);
    console.log('BUFF V14 veritabanı hazır. Tek SQL şeması ve V14 tabloları başarıyla doğrulandı.');
  } catch (err) {
    console.error('\nVERITABANI KURULUM HATASI:');
    console.error(err && err.message ? err.message : err);
    process.exitCode = 1;
  } finally {
    if (conn) await conn.end();
  }
}

run();
