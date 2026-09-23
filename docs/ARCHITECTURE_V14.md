# BUFF V14 Architecture

## Request plane
- **PHP REST API**: account, payments, admin, feed, gifts, recordings and business transactions.
- **Node.js realtime plane**: WebSocket `/realtime`, lightweight health/metrics and shared-session aware Node routes.
- **Shared authentication**: HS256 JWT + `shared_sessions` persistence. Redis accelerates session lookup when enabled; MySQL is the durable fallback.

## Media plane
All upload entry points use the V14 storage adapter. `STORAGE_PROVIDER=local` writes under `uploads/`; `s3`, `r2` and `minio` use the S3-compatible adapter. Public URLs should point to a CDN/object-storage public hostname in production.

Uploaded VOD/clip media creates a `job_queue` entry. `backend/worker.js` executes FFmpeg and writes HLS 360p/720p/1080p plus DASH 720p variants to `media_variants`. The worker is independent from the HTTP request path.

## Event/queue plane
- `job_queue`: durable async jobs with retry/backoff.
- Redis: optional fast signal for shared session/realtime work.
- `realtime_events`: database fallback/audit of application events.
- WebSocket: notification/feed/stream updates.

RabbitMQ/Kafka can replace the queue transport later without changing the public PHP endpoints; V14 deliberately keeps MySQL as the durable local fallback so XAMPP remains usable.

## Safety and finance
- PayTR callbacks verify the provider HMAC before money state changes.
- Balance/refund writes use database transactions and finance audit records.
- `finance_audit_logs` stores AES-256-GCM encrypted payloads and a chained row hash.
- Built-in text moderation is synchronous for severe content; external AI moderation is a queued webhook adapter.

## Product integrations
- live clip -> clip table -> feed -> realtime/chat event
- community goals updated by gifts
- channel/global loyalty and level/evolving avatar frame
- live predictions with loyalty points
- member-only feed visibility
- personalized discover via interaction weights
- behavioral live notifications

## Observability
- Node: `/metrics`
- PHP: `/backend/api/metrics.php`
- Health: `/backend/api/health.php` and Node `/api/health`
- `docker-compose.infrastructure.yml` provides Redis, MinIO, Prometheus/Grafana and optional ELK.
