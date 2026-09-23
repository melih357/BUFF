# BUFF V18

Güncel arayüz, canlı sohbet ve hediye düzeltmeleri: **[REVIZYON_V18.md](REVIZYON_V18.md)**.

## Önceki sürüm: BUFF V17

Güncel değişiklikler, görünürlük seçenekleri ve mevcut sunucuyu güncelleme adımları: **[REVIZYON_V17.md](REVIZYON_V17.md)**. Doğrulama: **[BUILD_VALIDATION_V17.txt](BUILD_VALIDATION_V17.txt)**.

V17 medya erişim kuralları eski V14 CDN/medya yönergelerinin önüne geçer. Aşağıda temel kurulum ve önceki mimari bilgileri korunmuştur.

BUFF V14 is a hybrid PHP + Node.js live-streaming/community platform. The package keeps local XAMPP fallback behavior while providing production adapters for Redis, S3/R2/MinIO, FFmpeg HLS/DASH, realtime WebSocket events, asynchronous workers, PayTR, moderation, observability and gamification.

## Included V14 layers

- Shared PHP/Node JWT sessions backed by `shared_sessions`; Redis acceleration with MySQL fallback.
- Local/S3/Cloudflare R2/MinIO media adapter and CDN-ready public URLs.
- Async `job_queue` worker; FFmpeg 360p/720p/1080p HLS + DASH generation for recordings/clips.
- PayTR callback HMAC validation, database transactions and encrypted hash-chained finance audit records.
- Built-in chat/comment moderation + external AI moderation webhook adapter.
- Prometheus metrics, Grafana provisioning and optional ELK stack.
- Live clipping -> Discover/feed/chat, community goals, live predictions, daily loyalty points, channel levels and evolving chat avatar frames.
- Member-only feed posts, personalized Discover and behavior-based notifications.
- GDPR/KVKK account anonymization while preserving legally required finance/audit records.
- Existing V13 admin/CMS/RBAC/support/reporting modules remain in the single V14 database schema.

Detailed architecture: `docs/ARCHITECTURE_V14.md`.

## Local XAMPP installation (Windows)

Requirements: XAMPP with PHP 8.2+ and MySQL/MariaDB, Node.js 20+, npm, and FFmpeg on `PATH` for VOD transcoding.

1. Extract the `BUFF` folder to `C:\xampp\htdocs\dnm` (or another folder under `htdocs`).
2. Start Apache and MySQL from XAMPP.
3. Open `.env`. The bundled file is configured for local development (`DB_DATABASE=buff_v126`, storage `local`, Redis off).
4. Run `KURULUM.bat`. It opens `install.php`, executes the single schema `database/BUFF_DATABASE_SETUP.sql`, creates/repairs the V14 tables and seeds the development admin.
5. In a terminal in the project root run `npm install` once. `KURULUM.bat` also offers this when npm is available.
6. Run `SERVISLERI_BASLAT.bat` to start the Node realtime server and async worker. Apache/MySQL must remain running.
7. Open `http://localhost/dnm/frontend/`.

Development admin defaults are `buffyonetim@gmail.com` / `admin123`. Change them before exposing the project to a network.

Redis and object storage are optional locally. With `REDIS_ENABLED=0` and `STORAGE_PROVIDER=local`, the platform uses MySQL/local-disk fallbacks.

## Production server migration (Ubuntu/Debian example)

1. Install Nginx/Apache, PHP-FPM 8.2+ (`pdo_mysql`, `curl`, `openssl`, `fileinfo`, `mbstring`), MariaDB/MySQL, Node.js 20+, npm, FFmpeg and Redis. Docker is optional.
2. Upload/extract the project to `/var/www/buff`; do **not** copy your local `.env` secrets between environments. Run `cp .env.production.example .env` and replace every `REPLACE_*` value.
3. Create the DB user/database or allow setup to create the database, then run `npm install --omit=dev` and `node backend/src/setup-db.js`. Alternatively import `database/BUFF_DATABASE_SETUP.sql` after replacing/choosing the configured DB name.
4. If local storage is used, grant the PHP/worker account write access to `uploads/`. For production, set `STORAGE_PROVIDER=r2`, `s3` or `minio` and configure a CDN/public base URL.
5. Set `PUBLIC_BASE_URL=https://your-domain`, `REALTIME_PUBLIC_URL=wss://your-domain/realtime`, `COOKIE_SECURE=1`, strong independent `APP_ENCRYPTION_KEY` and `JWT_SECRET`, real PayTR credentials, and Redis credentials.
6. Copy `docs/systemd/buff-node.service` and `docs/systemd/buff-worker.service` to `/etc/systemd/system/`, adjust paths/users if needed, then run `systemctl daemon-reload && systemctl enable --now buff-node buff-worker`.
7. Use `docs/nginx-buff.conf.example` as the site config. Keeping `/realtime` on the same public hostname lets the HttpOnly session cookie work with WebSocket authentication.
8. Configure TLS (for example Certbot), then make PayTR callback URLs HTTPS and publicly reachable.
9. Run `docker compose -f docker-compose.infrastructure.yml up -d redis minio prometheus grafana` only if you want the bundled infrastructure containers. For ELK add `--profile elk`.
10. Verify PHP health at `/backend/api/health.php`, Node health at `http://127.0.0.1:3000/api/health`, Node metrics at `:3000/metrics`, and PHP metrics at `/backend/api/metrics.php`.

## Object storage examples

- R2: `STORAGE_PROVIDER=r2`, endpoint `https://<account>.r2.cloudflarestorage.com`, region `auto`, public base set to your R2 custom domain/CDN.
- AWS S3: `STORAGE_PROVIDER=s3`, use the bucket region and endpoint.
- MinIO: `STORAGE_PROVIDER=minio`, path-style enabled, endpoint such as `http://127.0.0.1:9000`.

The application never needs raw card/CVV storage. PayTR credentials and callbacks remain server-side.

## Worker / queues

`npm run worker` processes `media_transcode`, moderation, email webhook, notification broadcast and storage cleanup jobs. HTTP requests only enqueue long-running work. Redis is used as an accelerator/signal; the MySQL `job_queue` is the durable fallback.

## Observability

`docker-compose.infrastructure.yml` includes Prometheus/Grafana. The `elk` profile adds Elasticsearch/Logstash/Kibana. Update Prometheus targets if your public folder is not `/dnm`.

## Final pre-production checklist

- Replace admin password and all secrets.
- Set `APP_DEBUG=0` and HTTPS-only cookies.
- Use a dedicated DB user with least privilege.
- Enable Redis authentication/firewalling; do not expose Redis/MinIO/Prometheus directly to the internet.
- Point uploads at object storage/CDN and test CORS/public URL policy.
- Enter real PayTR credentials and verify test -> live callback behavior before accepting money.
- Configure the AI moderation/email webhook providers if desired; with no provider configured, local fallbacks remain active.
- Back up the database and object-storage bucket; monitor worker failures and `finance_audit_logs`.

## Validation command

Linux/macOS: `bash scripts/verify.sh`

Windows: `KONTROL.bat`

These run PHP and JavaScript syntax checks and verify the required V14 schema/files. External services still need credentials/network access to perform live provider tests.
