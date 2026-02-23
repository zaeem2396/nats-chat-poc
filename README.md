# nats-chat-poc

Proof-of-concept Laravel chat backend with analytics and moderation using [zaeem2396/laravel-nats](https://github.com/zaeem2396/laravel-nats). Demonstrates publish/subscribe, wildcards, request-reply (RPC), NATS queue driver, JetStream streams and durable consumers, delayed jobs, and multiple connections.

## Tech stack

- Laravel 12, PHP 8.3+
- zaeem2396/laravel-nats
- NATS Server with JetStream
- MySQL or SQLite

## Subject structure

| Subject | Purpose |
|--------|---------|
| `chat.room.{roomId}.message` | Chat message published when user sends |
| `chat.room.{roomId}.deleted` | (Reserved) |
| `chat.room.*.message` | Moderation subscriber (single-level wildcard) |
| `chat.room.>` | JetStream stream + analytics (multi-level wildcard) |
| `user.rpc.preferences` | RPC: get user notification preferences |
| `notifications.email` | (Reserved) |

## Setup

### Local (no Docker)

1. Install NATS with JetStream: `nats-server -js -m 8222`
2. Clone and install: `composer install` (use path repo for laravel-nats if needed)
3. Copy `.env.example` to `.env`, set `QUEUE_CONNECTION=nats`, `NATS_HOST=127.0.0.1`, `NATS_QUEUE_DELAYED_ENABLED=true`
4. Create DB (e.g. SQLite): `touch database/database.sqlite`
5. Migrate: `php artisan migrate`
6. Start queue worker: `php artisan queue:work nats`
7. (Optional) Start moderation subscriber: `php artisan nats-chat:moderation`
8. (Optional) Start RPC responder: `php artisan nats-chat:rpc-responder`
9. (Optional) Start analytics worker: `php artisan nats-chat:analytics-worker`
10. Serve: `php artisan serve`

### Docker

1. Ensure `vendor` exists (run `composer install` on host if using path repo for laravel-nats).
2. `docker compose up -d`
3. `docker compose exec app php artisan migrate --force`
4. Queue worker runs in `queue` container. For moderation/RPC/analytics workers, run in separate terminals:
   - `docker compose exec app php artisan nats-chat:moderation`
   - `docker compose exec app php artisan nats-chat:rpc-responder`
   - `docker compose exec app php artisan nats-chat:analytics-worker`

API base: `http://localhost:8000` (or your app URL). API prefix: `/api`.

Subject layout is also documented in `config/nats_subjects.php`.

## API endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/rooms` | Create room `{ "name": "General" }` |
| POST | `/api/rooms/{id}/message` | Send message `{ "user_id": 1, "content": "Hello" }` |
| POST | `/api/rooms/{id}/schedule` | Schedule message (delayed job) `{ "user_id": 1, "content": "Later", "delay_minutes": 1 }` |
| GET | `/api/rooms/{id}/history` | Room message history |
| GET | `/api/analytics/room/{id}` | Room analytics (message_count) |

## Commands

- `php artisan queue:work nats` – Process NATS queue jobs (moderation, analytics, notifications, delayed)
- `php artisan nats-chat:moderation` – Subscribe to `chat.room.*.message`, dispatch ModerateMessageJob + SendNotificationJob
- `php artisan nats-chat:rpc-responder` – Respond to `user.rpc.preferences` with `{ "notifications_enabled": true }`
- `php artisan nats-chat:analytics-worker` – JetStream durable consumer `analytics-service` on stream `chat-stream`, dispatch ProcessAnalyticsJob
- `php artisan nats-chat:failed-jobs` – List failed NATS jobs from `failed_jobs` table

## Demo scenarios

### 1. Normal message flow

1. Create room: `POST /api/rooms` with `{"name":"Demo"}`
2. Send message: `POST /api/rooms/1/message` with `{"user_id":1,"content":"Hello"}`
3. Message is published to `chat.room.1.message`, stored in DB
4. If moderation subscriber is running, it receives (wildcard `chat.room.*.message`), dispatches ModerateMessageJob and SendNotificationJob
5. Queue worker processes jobs; SendNotificationJob calls RPC `user.rpc.preferences` if RPC responder is running
6. If analytics worker is running (JetStream stream `chat-stream` subject `chat.room.>`), it receives the message, dispatches ProcessAnalyticsJob, acks
7. `GET /api/analytics/room/1` shows incremented message_count

### 2. Message containing "fail-test"

Send `POST /api/rooms/1/message` with `{"user_id":1,"content":"This is a fail-test message"}`. ModerateMessageJob, ProcessAnalyticsJob, and SendNotificationJob will throw and retry (3 tries, 10s backoff), then land in `failed_jobs`. List with `php artisan nats-chat:failed-jobs`.

### 3. Durable consumer (analytics worker restart)

1. Start analytics worker: `php artisan nats-chat:analytics-worker`
2. Send a few messages so the worker processes them
3. Stop the worker (Ctrl+C), send more messages (they stay in JetStream)
4. Restart the worker; it resumes from last ack and processes the pending messages

### 4. Delayed message

`POST /api/rooms/1/schedule` with `{"user_id":1,"content":"Scheduled in 1 min","delay_minutes":1}`. Message is dispatched via SendChatMessageJob with `->delay(now()->addMinutes(1))`. Requires JetStream delayed queue enabled (`NATS_QUEUE_DELAYED_ENABLED=true`).

### 5. RPC preference disabling

Run RPC responder; in code change the response to `notifications_enabled: false` for a given user. Send a message; SendNotificationJob will call RPC and skip sending the notification.

## Architecture notes

- **Publish**: ChatMessageService publishes to `chat.room.{roomId}.message` and persists to `messages` table.
- **Wildcards**: Moderation uses `chat.room.*.message` (one token); JetStream stream uses `chat.room.>` (one or more).
- **Queue driver**: Jobs (ModerateMessageJob, ProcessAnalyticsJob, SendNotificationJob, SendChatMessageJob) use `QUEUE_CONNECTION=nats` with retries and backoff.
- **Failed jobs**: Stored in `failed_jobs`; list with `nats-chat:failed-jobs`.
- **JetStream**: Stream `chat-stream` captures `chat.room.>`. Durable consumer `analytics-service` used by analytics worker.
- **Multiple connections**: Default for chat/moderation; `analytics` connection used by analytics worker (`Nats::jetstream('analytics')`).
- **RPC**: No `Nats::reply()` helper; subscriber on `user.rpc.preferences` reads `getReplyTo()` and publishes response to that subject.

## License

MIT.
