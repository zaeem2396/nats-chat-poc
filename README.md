# NATS Chat PoC

Production-style Laravel chat backend demonstrating the **[zaeem2396/laravel-nats](https://github.com/zaeem2396/laravel-nats)** package: Pub/Sub, RPC, JetStream, queue driver, DLQ, metrics, and multi-worker scaling.

## Architecture Overview

- **API** receives send/schedule → persists message and publishes to `chat.room.{id}.message`.
- **Moderation** (`nats:consume` wildcard) and **JetStream** stream both receive messages; moderation dispatches queue jobs (moderation, notification); analytics worker pulls from stream and dispatches analytics job.
- **Queue workers** (`nats:work`) process jobs; failed jobs go to **DLQ** (`chat.dlq`) and can be stored in `failed_messages` via `nats-chat:dlq-store`.
- **Metrics** (processed/failed/retries/avg time) are exposed at `GET /api/metrics`.

See **[docs/DOCUMENTATION.md](docs/DOCUMENTATION.md)** for a full diagram and data flow.

## What This PoC Demonstrates

- **Publish/Subscribe** - Chat messages published to NATS subjects; subscribers for moderation and analytics
- **Wildcards** - Single-level (`chat.room.*.message`) and multi-level (`chat.room.>`) subscriptions
- **Request/Reply (RPC)** - User preferences via `user.rpc.preferences`
- **NATS as Laravel queue driver** - Jobs processed with `nats:work` (retries, backoff, failed jobs)
- **JetStream** - Streams, durable consumers, pull consumption, acknowledgements
- **Delayed jobs** - Scheduled messages via JetStream delayed queue
- **Multiple NATS connections** - Default + analytics connection
- **Package Artisan commands** - `nats:work`, `nats:consume` with handler classes
- **DLQ** - Failed jobs published to `chat.dlq`, stored in DB, `GET /api/dlq`
- **Metrics** - `GET /api/metrics` (processed, failed, retries, avg processing time)
- **v2 envelope (basis-company/nats)** - `NatsV2::publish` adds `{ id, type: <subject>, version, data }`; `nats-v2-listen` container prints JSON lines; consumers use `EventPayload::unwrap()` for flat `data`; idempotency via `message_id`
- **Multi-worker** - Two queue workers in Docker (same queue = load balanced)

## Feature Mapping (laravel-nats vs this project)

| laravel-nats feature | This project |
|----------------------|--------------|
| `NatsV2::publish` (v2 envelope) | ChatMessageService publishes to `chat.room.{id}.message` |
| `nats:v2:listen` | **nats-v2-listen** container (same wildcard as moderation; demo of basis subscriber) |
| `nats:consume` + handler | ModerationMessageHandler, UserPreferencesRpcHandler |
| `nats:work` (queue) | queue + queue-2 containers; ModerateMessageJob, SendNotificationJob, ProcessAnalyticsJob |
| JetStream stream/consumer | ChatStreamBootstrap (chat-stream), DlqStreamBootstrap (dlq), analytics-worker |
| Delayed jobs (JetStream) | Schedule message endpoint |
| Request/reply | SendNotificationJob → `user.rpc.preferences` → UserPreferencesRpcHandler |
| Dead letter queue | `chat.dlq` → failed_messages table, `GET /api/dlq` |
| `nats_basis` queue driver (v1.4+) | Optional `QUEUE_CONNECTION=nats_basis` in `.env` (see `config/queue.php`) |
| `NatsV2::ping` / `php artisan nats:ping` | `GET /api/health` → `nats_v2_reachable` |

## Failure Scenarios

- **Retries:** Jobs use `tries=3` and `backoff`; JetStream consumer has `ack_wait` and `max_deliver` (config: `NATS_JETSTREAM_ACK_WAIT`, `NATS_JETSTREAM_MAX_DELIVER`).
- **DLQ:** After max retries, job is published to `chat.dlq`. Run `nats-chat:dlq-store` to persist to `failed_messages`; list via `GET /api/dlq`.
- **Worker crash:** Queue jobs are re-delivered by NATS when a worker dies; JetStream consumer resumes from last ack on restart.

## Scaling

- **Multiple workers:** Docker runs two `nats:work` containers (queue, queue-2) on the same queue name `default`. NATS distributes messages between them (queue group).
- **Add more:** Add more services in `docker-compose.yml` with the same `command: php artisan nats:work --queue=default`.

## Why NATS (vs Redis / Kafka)

- **NATS:** Lightweight, at-most-once or JetStream for persistence, low latency, easy to run. Good for event-driven apps and queues at moderate scale.
- **Redis (queues):** Simple, in-memory; no built-in replay or durable log.
- **Kafka:** Durable log, high throughput; heavier ops and resource use. Prefer NATS when you want persistence and ordering without Kafka’s complexity.

## Tech Stack

- **Laravel 12**, PHP 8.3+
- **zaeem2396/laravel-nats**
- **NATS Server** with JetStream
- **MySQL 8** (Docker) or SQLite (local)
- **phpMyAdmin** (Docker) for database access

## Quick Start (Docker)

Ports are set to avoid clashes with other projects:

| Service     | URL / Port              |
|------------|--------------------------|
| Laravel API| http://localhost:**8090** |
| phpMyAdmin | http://localhost:**8091** |
| MySQL      | localhost:**3307**       |
| NATS       | **4223** (client), **8224** (monitor) |

If **8090**, **4223**, or **8224** are already in use on the host, set env vars when running Compose (Docker still uses internal `nats:4222`). You can add `APP_HOST_PORT`, `NATS_HOST_CLIENT_PORT`, and `NATS_HOST_MONITOR_PORT` to a `.env` file next to `docker-compose.yml`, or pass them inline, e.g.:

`APP_HOST_PORT=18091 docker compose up -d`  
then `docker compose up -d --force-recreate app` so `APP_URL` matches.

```bash
# Install dependencies (laravel-nats ^1.4 from Packagist)
composer install

# Use MySQL in Docker: copy Docker env so the app uses MySQL (not SQLite from .env)
cp .env.docker .env
# Or merge DB_* and NATS_* from .env.docker into your existing .env

docker compose up -d

# Run migrations and clear config cache
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate --force
```

Containers started:

- **app** - Laravel (default host port **8090**, or `APP_HOST_PORT` if set)
- **queue**, **queue-2** - `php artisan nats:work` (same queue = load balanced)
- **moderation** - `nats:consume "chat.room.*.message"` with `ModerationMessageHandler`
- **nats-v2-listen** - `nats:v2:listen "chat.room.*.message"` (v2 subscriber stack / basis client)
- **rpc-responder** - `nats:consume "user.rpc.preferences"` with `UserPreferencesRpcHandler`
- **analytics** - JetStream durable consumer (analytics worker)
- **mysql** - MySQL on host port 3307
- **phpmyadmin** - http://localhost:8091 (server: `mysql`, user: `nats_chat`, password: `secret`)
- **nats** - NATS + JetStream on 4223 / 8224

**How to run and usage:** See **[docs/RUNNING.md](docs/RUNNING.md)** for step-by-step run, API usage, and all Artisan commands.

### Try It

Open **http://localhost:8090** (or your `APP_HOST_PORT` if you changed it). You should see the **chat** UI.

From the UI you can: create rooms, send messages, schedule delayed messages, view history and analytics, and trigger the fail-test message (for failed jobs demo). No curl needed.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Liveness: `status`, `nats_v2_reachable` (`NatsV2::ping`) |
| GET | `/api/rooms` | List rooms |
| POST | `/api/rooms` | Create room `{ "name": "General" }` |
| POST | `/api/rooms/{id}/message` | Send message `{ "user_id": 1, "content": "Hello" }` |
| POST | `/api/rooms/{id}/schedule` | Schedule message (delayed job) `{ "user_id": 1, "content": "Later", "delay_minutes": 1 }` |
| GET | `/api/rooms/{id}/history` | Room message history |
| GET | `/api/analytics/room/{id}` | Room analytics (message_count) |
| GET | `/api/dlq` | List failed messages (DLQ; includes `error_message`, `attempts`) `?per_page=20` |
| GET | `/api/metrics` | Metrics: `total_messages`, `processed_messages`, `failed_messages`, `retries`, `avg_processing_time` |

## Artisan Commands

### Package (laravel-nats)

- `php artisan nats:work` - NATS queue worker (legacy `nats` driver)
- `php artisan nats:ping` - v2 basis connection ping (CLI health check)
- `php artisan nats:v2:listen "{subject}"` - v2 subscriber loop (envelope-aware)
- `php artisan nats:consume "{subject}" --handler=ClassName` - Subject consumer with handler

### This project

- `php artisan nats-chat:analytics-worker` - JetStream durable consumer for analytics
- `php artisan nats-chat:dlq-bootstrap` - Create DLQ stream (run once)
- `php artisan nats-chat:dlq-store` - Consume DLQ → failed_messages table
- `php artisan nats-chat:load-test [--count=1000] [--use-http]` - Load test
- `php artisan nats-chat:failed-jobs` - List failed NATS jobs

## Subject Structure

| Subject | Purpose |
|--------|---------|
| `chat.room.{roomId}.message` | Chat message (published on send) |
| `chat.room.*.message` | Moderation consumer (wildcard) |
| `chat.room.>` | JetStream stream + analytics |
| `chat.dlq` | Dead letter queue (failed jobs) |
| `user.rpc.preferences` | RPC: user notification preferences |

## Demo Guide (Step-by-Step)

1. **Start stack:** `docker compose up -d` then `docker compose exec app php artisan migrate --force`
2. **Web UI:** Open http://localhost:8090 → create room → send message → Refresh (see history)
3. **Schedule:** Send a message with delay 1 min → wait → Refresh
4. **Metrics:** `curl -s http://localhost:8090/api/metrics`
5. **Fail-test:** In UI click "Send fail-test message" → `docker compose exec app php artisan nats-chat:failed-jobs`
6. **Load test:** `docker compose exec app php artisan nats-chat:load-test --count=200`
7. **NATS monitor:** http://localhost:8224 (connections, throughput)

## Testing

- **Manual (API):** See **[docs/TESTING.md](docs/TESTING.md)** for curl examples and demo scenarios (happy path, failed jobs, delayed messages).
- **Quick script:** `./test-manual.sh` (or `BASE_URL=http://localhost:8090 ./test-manual.sh`).
- **Automated:** `composer test` or `docker compose exec app php artisan test` (requires PHP with pdo_sqlite).

## Documentation

- **[docs/RUNNING.md](docs/RUNNING.md)** - How to run (Docker + local), API usage, all Artisan commands.
- **[docs/DOCUMENTATION.md](docs/DOCUMENTATION.md)** - Architecture, API reference, NATS monitoring, event versioning, config profiles, troubleshooting.
- **[docs/TESTING.md](docs/TESTING.md)** - Manual and automated testing.

## Local Setup (No Docker)

1. NATS with JetStream: `nats-server -js -m 8222`
2. `composer install`, copy `.env.example` to `.env`, set `QUEUE_CONNECTION=nats`, `NATS_HOST=127.0.0.1`, `NATS_QUEUE_DELAYED_ENABLED=true`
3. Database: `touch database/database.sqlite` (or MySQL), then `php artisan migrate`
4. Run in separate terminals: `php artisan serve`, `php artisan nats:work`, `php artisan nats:consume "chat.room.*.message" --handler=App\Handlers\ModerationMessageHandler`, `php artisan nats:consume "user.rpc.preferences" --handler=App\Handlers\UserPreferencesRpcHandler`, `php artisan nats-chat:analytics-worker`

## Development

- Tests: `composer test`
- Code style: `composer format`
- Static analysis: `composer analyse`

## License

MIT.
