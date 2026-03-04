# How to Run and Usage

## Quick start (Docker)

```bash
# 1. Clone and install
git clone https://github.com/zaeem2396/nats-chat-poc.git && cd nats-chat-poc
composer install

# 2. Use Docker env (MySQL + NATS)
cp .env.docker .env
# Or: merge DB_* and NATS_* from .env.docker into your .env

# 3. Start all services
docker compose up -d

# 4. Migrate and (optional) bootstrap DLQ stream
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate --force
docker compose exec app php artisan nats-chat:dlq-bootstrap
```

**Services:**

| Service    | Purpose |
|-----------|---------|
| **app**   | Laravel API + web UI → http://localhost:8090 |
| **queue** | NATS queue worker (`nats:work`) |
| **queue-2** | Second queue worker (same queue = load balanced) |
| **moderation** | Subscribes to `chat.room.*.message`, dispatches jobs |
| **rpc-responder** | Replies to `user.rpc.preferences` |
| **analytics** | JetStream consumer for chat stream |
| **mysql** | Database (host port 3307) |
| **phpmyadmin** | http://localhost:8091 |
| **nats** | NATS + JetStream (client 4223, monitor 8224) |

---

## Usage: Web UI

1. Open **http://localhost:8090**
2. Create a room (sidebar): enter name → Create.
3. Select a room → send message (User ID ≥ 1, content) → Send.
4. Schedule message: set delay (minutes) → Schedule.
5. Refresh to see history and analytics count.
6. **Fail-test:** click "Send fail-test message" to trigger job failures → then list failed jobs (see below).

---

## Usage: API

Base URL: **http://localhost:8090/api**

| Method | Endpoint | Body (JSON) |
|--------|----------|--------------|
| GET | `/rooms` | - |
| POST | `/rooms` | `{ "name": "General" }` |
| POST | `/rooms/{id}/message` | `{ "user_id": 1, "content": "Hello" }` |
| POST | `/rooms/{id}/schedule` | `{ "user_id": 1, "content": "Later", "delay_minutes": 1 }` |
| GET | `/rooms/{id}/history` | - |
| GET | `/analytics/room/{id}` | - |
| GET | `/dlq` | - (optional `?per_page=20`). Response: paginated list with `id`, `subject`, `payload`, `error_message`, `attempts`, `created_at`. |
| GET | `/metrics` | -. Response: `total_messages`, `processed_messages`, `failed_messages`, `retries`, `avg_processing_time` (ms). |

**Example (curl):**

```bash
# Create room
curl -s -X POST http://localhost:8090/api/rooms -H "Content-Type: application/json" -d '{"name":"Demo"}'

# Send message (use room id from above)
curl -s -X POST http://localhost:8090/api/rooms/1/message -H "Content-Type: application/json" -d '{"user_id":1,"content":"Hello"}'

# Room history
curl -s http://localhost:8090/api/rooms/1/history

# Analytics
curl -s http://localhost:8090/api/analytics/room/1

# DLQ (failed messages)
curl -s http://localhost:8090/api/dlq

# Metrics
curl -s http://localhost:8090/api/metrics
```

---

## Artisan commands

**Inside Docker:** `docker compose exec app php artisan <command>`

| Command | Description |
|---------|-------------|
| `nats:work --tries=3 --sleep=1 --queue=default` | Process NATS queue jobs |
| `nats:consume "chat.room.*.message" --handler=App\Handlers\ModerationMessageHandler` | Moderation subscriber |
| `nats:consume "user.rpc.preferences" --handler=App\Handlers\UserPreferencesRpcHandler` | RPC responder |
| `nats-chat:analytics-worker` | JetStream analytics consumer |
| `nats-chat:dlq-bootstrap` | Create DLQ stream (run once) |
| `nats-chat:dlq-store` | Consume DLQ → `failed_messages` table |
| `nats-chat:load-test [--count=1000] [--use-http] [--base-url=http://localhost:8090]` | Load test: send messages, report throughput |
| `nats-chat:failed-jobs [--connection=nats]` | List failed jobs from DB |

**Load test examples:**

```bash
# In-process (fast, 100 messages default)
docker compose exec app php artisan nats-chat:load-test --count=500

# Via HTTP (hits API)
docker compose exec app php artisan nats-chat:load-test --count=200 --use-http --base-url=http://localhost:8090
```

---

## Optional: DLQ store worker

To persist failed queue messages to the `failed_messages` table and expose them via `GET /api/dlq`:

1. Bootstrap once: `php artisan nats-chat:dlq-bootstrap`
2. Run the store worker (e.g. in a separate container or terminal): `php artisan nats-chat:dlq-store`

Then when jobs fail after max retries, they are published to `chat.dlq`; the store worker consumes them and inserts into `failed_messages`.

---

## NATS monitoring

- **Monitor URL:** http://localhost:8224 (Docker).
- Use it to see connections (app, queue, moderation, rpc, analytics) and message throughput.

---

## Local run (no Docker)

1. NATS with JetStream: `nats-server -js -m 8222`
2. `composer install`, copy `.env.example` to `.env`, set `NATS_HOST=127.0.0.1`, `NATS_PORT=4222`, `QUEUE_CONNECTION=nats`, `NATS_QUEUE_DELAYED_ENABLED=true`
3. `php artisan migrate`
4. Run in separate terminals: `php artisan serve`, `php artisan nats:work`, moderation consume, rpc consume, `php artisan nats-chat:analytics-worker`
5. Optional: `php artisan nats-chat:dlq-bootstrap` then `php artisan nats-chat:dlq-store`

---

## Tests

```bash
# Host (needs pdo_sqlite)
composer test

# Docker
docker compose exec app php artisan test
```
