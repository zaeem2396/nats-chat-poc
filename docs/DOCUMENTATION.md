# NATS Chat PoC — Full Documentation

This document describes the **NATS Chat Proof of Concept**: a Laravel-based chat backend that demonstrates the [zaeem2396/laravel-nats](https://github.com/zaeem2396/laravel-nats) package in a realistic scenario. It is suitable for proposals, demos, and technical evaluation.

---

## 1. Overview

### 1.1 Purpose

- Showcase **Laravel NATS** integration in a single, runnable project.
- Demonstrate **publish/subscribe**, **wildcards**, **request-reply (RPC)**, **NATS as queue driver**, **JetStream** streams and durable consumers, **delayed jobs**, and **multiple NATS connections**.
- Use the package’s **full Artisan surface**: `nats:work`, `nats:consume` (with handlers), plus custom analytics worker for JetStream pull consumption.

### 1.2 Tech Stack

| Component        | Technology                          |
|-----------------|-------------------------------------|
| Framework       | Laravel 12, PHP 8.3+                |
| Messaging       | zaeem2396/laravel-nats, NATS 2.x    |
| NATS features   | Core pub/sub, JetStream             |
| Database        | MySQL 8.0 (Docker) or SQLite (local)|
| Admin UI        | phpMyAdmin (Docker)                 |

### 1.3 High-Level Architecture

```
┌─────────────┐     POST /api/rooms/{id}/message      ┌──────────────┐
│   Client    │ ────────────────────────────────────► │   Laravel    │
└─────────────┘                                        │     App     │
                                                       └──────┬──────┘
                                                              │
         ┌────────────────────────────────────────────────────┼────────────────────────────────────────────────────┐
         │                                                    │                                                    │
         ▼                                                    ▼                                                    ▼
┌─────────────────┐                              ┌─────────────────────┐                              ┌─────────────────┐
│ Nats::publish   │                              │  Persist message    │                              │  JetStream       │
│ chat.room.{id}  │                              │  (messages table)   │                              │  stream         │
│ .message        │                              └─────────────────────┘                              │  chat-stream     │
└────────┬────────┘                                                                                     └────────┬────────┘
         │                                                                                                       │
         │  chat.room.*.message                          user.rpc.preferences                                    │ durable
         │  (wildcard)                                    (RPC)                                                  │ consumer
         ▼                                                ▼                                                      ▼
┌─────────────────────┐                        ┌─────────────────────┐                              ┌─────────────────────┐
│ nats:consume        │                        │ nats:consume        │                              │ analytics-worker   │
│ ModerationHandler   │                        │ UserPreferencesRpc   │                              │ (JetStream pull)   │
│ → ModerateMessageJob│                        │ Handler → reply      │                              │ → ProcessAnalytics │
│ → SendNotificationJob                        └─────────────────────┘                              │   Job              │
└──────────┬──────────┘                                                                             └──────────┬──────────┘
           │                                                                                                      │
           ▼                                                                                                      ▼
┌─────────────────────┐                                                                              ┌─────────────────────┐
│ nats:work           │                                                                              │ analytics table     │
│ (NATS queue driver) │                                                                              │ (message_count)     │
└─────────────────────┘                                                                              └─────────────────────┘
```

---

## 2. Subject Structure

| Subject                      | Purpose |
|-----------------------------|---------|
| `chat.room.{roomId}.message`| Chat message published when a user sends a message |
| `chat.room.{roomId}.deleted`| Reserved for future use |
| `chat.room.*.message`      | Moderation subscriber (single-level wildcard) |
| `chat.room.>`              | JetStream stream + analytics (multi-level wildcard) |
| `chat.dlq`                 | Dead letter queue (failed jobs after max retries) |
| `user.rpc.preferences`     | RPC: get user notification preferences |
| `notifications.email`      | Reserved |

---

## 3. Services and Ports (Docker)

Ports are chosen to avoid clashes with other projects:

| Service     | Container port (internal) | Host port | URL / access |
|------------|----------------------------|-----------|--------------|
| Laravel app| 8000                       | **8090**  | http://localhost:8090 |
| MySQL      | 3306                       | **3307**  | localhost:3307 (e.g. from host CLI) |
| phpMyAdmin | 80                         | **8091**  | http://localhost:8091 |
| NATS       | 4222 (client)              | **4223**  | localhost:4223 |
| NATS       | 8222 (monitor)             | **8224**  | http://localhost:8224 |

- **API base:** `http://localhost:8090` (prefix: `/api`).
- **phpMyAdmin:** http://localhost:8091 — Server: `mysql`, User: `nats_chat`, Password: `secret` (or root/`root`).

---

## 4. Quick Start (Docker)

### 4.1 Prerequisites

- Docker and Docker Compose.
- On the host: `composer install` (and path repo for `zaeem2396/laravel-nats` if used).

### 4.2 Start Everything

Ensure the app uses MySQL in Docker (the host `.env` may have `DB_CONNECTION=sqlite`). Either copy the Docker env file or merge its values into your `.env`:

```bash
cp .env.docker .env
# Or merge DB_* and NATS_* from .env.docker into your .env
```

Then:

```bash
docker compose up -d
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate --force
```

Containers started:

- **app** — Laravel HTTP server (port 8090).
- **queue** — `php artisan nats:work` (NATS queue jobs).
- **moderation** — `php artisan nats:consume "chat.room.*.message" --handler=App\Handlers\ModerationMessageHandler`.
- **rpc-responder** — `php artisan nats:consume "user.rpc.preferences" --handler=App\Handlers\UserPreferencesRpcHandler`.
- **analytics** — `php artisan nats-chat:analytics-worker` (JetStream durable consumer).
- **mysql** — MySQL 8.0 (port 3307).
- **phpmyadmin** — phpMyAdmin (port 8091).
- **nats** — NATS with JetStream (ports 4223, 8224).

### 4.3 Verify

- App: http://localhost:8090  
- phpMyAdmin: http://localhost:8091  
- NATS monitor: http://localhost:8224  

---

## 5. API Reference

Base URL: `http://localhost:8090/api` (Docker). All request/response bodies are JSON.

### 5.1 Create room

```http
POST /api/rooms
Content-Type: application/json

{ "name": "General" }
```

**Response:** `{ "id": 1, "name": "General", ... }`

### 5.2 Send message

```http
POST /api/rooms/{id}/message
Content-Type: application/json

{ "user_id": 1, "content": "Hello" }
```

**Response:** `{ "id": 1, "room_id": 1, "user_id": 1, "content": "Hello", ... }`

Publishes to `chat.room.{id}.message`, triggers moderation (if running), RPC (if running), and analytics (if running).

### 5.3 Schedule message (delayed job)

```http
POST /api/rooms/{id}/schedule
Content-Type: application/json

{ "user_id": 1, "content": "Later", "delay_minutes": 1 }
```

**Response:** `{ "scheduled": true, ... }`

Uses JetStream delayed queue; message is sent after the given delay.

### 5.4 Room history

```http
GET /api/rooms/{id}/history
```

**Response:** `{ "data": [ { "id", "room_id", "user_id", "content", "created_at" }, ... ] }`

### 5.5 Room analytics

```http
GET /api/analytics/room/{id}
```

**Response:** `{ "room_id": 1, "message_count": 42 }`

### 5.6 Dead Letter Queue (DLQ)

```http
GET /api/dlq?per_page=20
```

**Response:** Paginated list of failed messages from the `failed_messages` table. Each item includes: `id`, `subject`, `payload` (JSON), `error_message`, `attempts`, `original_queue`, `original_connection`, `failed_at`, `created_at`. When jobs fail after max retries, they are published to `chat.dlq`; the `nats-chat:dlq-store` worker consumes and stores them here.

### 5.7 Metrics

```http
GET /api/metrics
```

**Response:** `{ "total_messages": 0, "processed_messages": 0, "failed_messages": 0, "retries": 0, "avg_processing_time": 0 }` (cache-backed; resets when cache expires). `avg_processing_time` is in milliseconds.

---

## 6. NATS Monitoring

With the monitor enabled (`-m 8222` in Docker, exposed as **8224** on the host):

- **URL:** http://localhost:8224
- **Connections:** Number of clients connected (app, queue workers, moderation, rpc, analytics).
- **Subscriptions:** Per-connection subscriptions (e.g. `chat.room.*.message`, `user.rpc.preferences`).
- **Throughput:** Messages in/out per second.

Use this to verify workers are connected and to observe traffic when sending messages or running the load test.

---

## 7. Artisan Commands Used

### 7.1 Package commands (laravel-nats)

| Command | Purpose |
|--------|---------|
| `php artisan nats:work` | NATS queue worker (replaces `queue:work nats`). Processes ModerateMessageJob, SendNotificationJob, ProcessAnalyticsJob, SendChatMessageJob. |
| `php artisan nats:consume "chat.room.*.message" --handler=App\Handlers\ModerationMessageHandler` | Subscribe to chat messages (wildcard), dispatch moderation and notification jobs. |
| `php artisan nats:consume "user.rpc.preferences" --handler=App\Handlers\UserPreferencesRpcHandler` | RPC responder: reply to preference requests. |

### 7.2 Project commands

| Command | Purpose |
|--------|---------|
| `php artisan nats-chat:analytics-worker` | JetStream durable consumer `analytics-service` on stream `chat-stream`; dispatches ProcessAnalyticsJob. |
| `php artisan nats-chat:failed-jobs [--connection=nats]` | List failed NATS jobs from `failed_jobs` table. |
| `php artisan nats-chat:dlq-bootstrap` | Create JetStream stream/consumer for `chat.dlq` (run once). |
| `php artisan nats-chat:dlq-store` | Consume from DLQ stream, store in `failed_messages` table. |
| `php artisan nats-chat:load-test [--count=100] [--use-http] [--base-url=...]` | Send messages (in-process or via HTTP), report success/failure and throughput. |

### 7.3 Optional: JetStream management (package)

```bash
php artisan nats:stream:list
php artisan nats:stream:info chat-stream
php artisan nats:consumer:list chat-stream
php artisan nats:jetstream:status
```

---

## 8. Event Versioning, Idempotency, and Logging

- **Event envelope:** Every published message follows `{ "id": "<uuid>", "type": "chat.message.created", "version": "v1", "data": { ... } }`. Handlers unwrap via `EventPayload::unwrap()` so jobs receive flat `data`. The top-level `id` matches `data.message_id`.
- **Idempotency:** Jobs use `message_id` and cache (`processed_message:{id}`, `processed_analytics:{id}`) to skip duplicate processing.
- **Structured logging:** NATS-related logs use a standard shape: `event`, `subject`, `status` (success | failed | retry), `attempt`, `duration_ms`, `error`. Queue jobs log on success, failure, and each retry.

---

## 9. Ordering Strategy (Per-Room)

- **Approach:** One subject per room (`chat.room.{roomId}.message`) and NATS Core queue subscriptions (same queue name) deliver each message to one consumer. Within a single consumer, messages for the same room are processed in order.
- **Tradeoff:** Multiple queue workers (same queue) share load but order across workers is not guaranteed per room. For strict per-room ordering, use a single consumer per room or a JetStream consumer with a filter per room.

---

## 10. Config Profiles

- **local:** `APP_ENV=local`, no NATS auth, SQLite or local MySQL.
- **docker:** Use `.env.docker` or merge: `DB_HOST=mysql`, `NATS_HOST=nats`, `NATS_QUEUE_DELAYED_ENABLED=true`.
- **production:** Set `NATS_USER`/`NATS_PASSWORD` or `NATS_TOKEN`, `APP_DEBUG=false`, consider TLS in `config/nats.php`.

---

## 11. Demo Scenarios

### 11.1 End-to-end message flow

1. Create room: `POST /api/rooms` with `{"name":"Demo"}`.
2. Send message: `POST /api/rooms/1/message` with `{"user_id":1,"content":"Hello"}`.
3. Message is stored and published to `chat.room.1.message`.
4. Moderation consumer receives it (wildcard), dispatches ModerateMessageJob and SendNotificationJob.
5. Queue worker processes jobs; SendNotificationJob calls RPC `user.rpc.preferences` (RPC responder replies).
6. Analytics worker consumes from JetStream, dispatches ProcessAnalyticsJob, acks.
7. Check analytics: `GET /api/analytics/room/1` (message_count incremented).

### 11.2 Failed jobs and retries

Send: `POST /api/rooms/1/message` with `{"user_id":1,"content":"This is a fail-test message"}`.

Jobs throw on purpose, retry (3 tries, backoff), then land in `failed_jobs`. List:

```bash
docker compose exec app php artisan nats-chat:failed-jobs
```

### 11.3 Durable consumer (analytics)

1. Ensure analytics container is running (or run manually: `php artisan nats-chat:analytics-worker`).
2. Send a few messages so the worker processes them.
3. Stop the worker, send more messages (they remain in JetStream).
4. Restart the worker; it resumes from last ack and processes pending messages.

### 11.4 Delayed message

```bash
curl -s -X POST http://localhost:8090/api/rooms/1/schedule \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"content":"Scheduled in 1 min","delay_minutes":1}'
```

Message appears in the room after the delay (JetStream delayed queue).

### 11.5 RPC preference

With the RPC responder running, SendNotificationJob calls `user.rpc.preferences`. To simulate “notifications off”, change the response in `App\Handlers\UserPreferencesRpcHandler` to `notifications_enabled: false` for a given user; the job will skip sending the notification.

---

## 12. Local Development (No Docker)

1. Start NATS with JetStream: `nats-server -js -m 8222` (or use ports 4223/8224 if needed).
2. `composer install`; copy `.env.example` to `.env`; set `NATS_HOST=127.0.0.1`, `NATS_PORT=4222` (or 4223), `QUEUE_CONNECTION=nats`, `NATS_QUEUE_DELAYED_ENABLED=true`.
3. Database: SQLite `touch database/database.sqlite` or MySQL; run `php artisan migrate`.
4. In separate terminals:
   - `php artisan serve` (or `php artisan serve --port=8090`)
   - `php artisan nats:work --tries=3 --sleep=1`
   - `php artisan nats:consume "chat.room.*.message" --handler=App\Handlers\ModerationMessageHandler`
   - `php artisan nats:consume "user.rpc.preferences" --handler=App\Handlers\UserPreferencesRpcHandler`
   - `php artisan nats-chat:analytics-worker`
5. Use API at http://localhost:8000 (or 8090). For MySQL with phpMyAdmin, run MySQL and phpMyAdmin yourself (e.g. different ports to avoid clashes).

---

## 13. Configuration Summary

- **Queue:** `config/queue.php` — connection `nats`, optional `dead_letter_queue`, JetStream `delayed` (stream, subject_prefix, consumer).
- **NATS:** `config/nats.php` — connections `default` and `analytics`; JetStream and queue options. Authentication: `NATS_USER`, `NATS_PASSWORD`, or `NATS_TOKEN` (optional).
- **Subjects:** `config/nats_subjects.php` — subject name constants.

Environment variables used (see `.env.example`):

- `QUEUE_CONNECTION=nats`
- `NATS_HOST`, `NATS_PORT`, `NATS_QUEUE_DELAYED_ENABLED`
- `NATS_USER`, `NATS_PASSWORD`, `NATS_TOKEN` (optional; for NATS auth)
- `NATS_JETSTREAM_ACK_WAIT`, `NATS_JETSTREAM_MAX_DELIVER` (consumer retry)
- `NATS_ANALYTICS_HOST`, `NATS_ANALYTICS_PORT` (for analytics connection)
- `NATS_QUEUE_DLQ` (optional, for dead-letter subject, default `chat.dlq`)
- DB_* for MySQL/SQLite

---

## 14. Package Features Demonstrated

| Feature | How |
|--------|-----|
| Publish | `Nats::publish()` in ChatMessageService / ChatMessagePublisher |
| Subscribe | `nats:consume` with ModerationMessageHandler, UserPreferencesRpcHandler |
| Request/Reply | `Nats::request()` in SendNotificationJob; RPC handler publishes to reply subject |
| Queue driver | Jobs on `nats` connection; `nats:work` |
| JetStream | Stream/consumer bootstrap; analytics worker pull + ack |
| Delayed jobs | Schedule endpoint; `NATS_QUEUE_DELAYED_ENABLED=true` |
| Multiple connections | Default + `analytics` for analytics worker |
| Wildcards | `chat.room.*.message`, `chat.room.>` |
| Artisan | `nats:work`, `nats:consume` with `--handler=` |

---

## 15. Troubleshooting

- **Port in use:** Change host ports in `docker-compose.yml` (e.g. 8090→8092, 3307→3308, 8091→8092, 4223→4225, 8224→8226).
- **Queue jobs not running:** Ensure `queue` container is up (`nats:work`). Check NATS and app logs.
- **Analytics not updating:** Ensure `analytics` container is up; stream/consumer are created on first run (ChatStreamBootstrap).
- **phpMyAdmin:** Use server `mysql`, user `nats_chat`, password `secret`; or root/`root` if allowed.
- **Failed jobs:** `php artisan nats-chat:failed-jobs`; inspect `failed_jobs` table or DLQ if configured.

---

## 16. License

MIT.
