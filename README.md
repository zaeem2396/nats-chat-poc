# NATS Chat PoC

Proof-of-concept Laravel chat backend demonstrating the **[zaeem2396/laravel-nats](https://github.com/zaeem2396/laravel-nats)** package. Suitable for proposals, demos, and technical evaluation.

## What This PoC Demonstrates

- **Publish/Subscribe** — Chat messages published to NATS subjects; subscribers for moderation and analytics
- **Wildcards** — Single-level (`chat.room.*.message`) and multi-level (`chat.room.>`) subscriptions
- **Request/Reply (RPC)** — User preferences via `user.rpc.preferences`
- **NATS as Laravel queue driver** — Jobs processed with `nats:work` (retries, backoff, failed jobs)
- **JetStream** — Streams, durable consumers, pull consumption, acknowledgements
- **Delayed jobs** — Scheduled messages via JetStream delayed queue
- **Multiple NATS connections** — Default + analytics connection
- **Package Artisan commands** — `nats:work`, `nats:consume` with handler classes

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

```bash
# Install dependencies (laravel-nats is pulled from Packagist / https://github.com/zaeem2396/laravel-nats)
composer install

# Use MySQL in Docker: copy Docker env so the app uses MySQL (not SQLite from .env)
cp .env.docker .env
# Or merge DB_* and NATS_* from .env.docker into your existing .env

# Start all services
docker compose up -d

# Run migrations and clear config cache
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate --force
```

Containers started:

- **app** — Laravel on port 8090
- **queue** — `php artisan nats:work`
- **moderation** — `nats:consume "chat.room.*.message"` with `ModerationMessageHandler`
- **rpc-responder** — `nats:consume "user.rpc.preferences"` with `UserPreferencesRpcHandler`
- **analytics** — JetStream durable consumer (analytics worker)
- **mysql** — MySQL on host port 3307
- **phpmyadmin** — http://localhost:8091 (server: `mysql`, user: `nats_chat`, password: `secret`)
- **nats** — NATS + JetStream on 4223 / 8224

### Try It

Open the **web UI** in your browser:

**http://localhost:8090**

From the UI you can: create rooms, send messages, schedule delayed messages, view history and analytics, and trigger the fail-test message (for failed jobs demo). No curl needed.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/rooms` | Create room `{ "name": "General" }` |
| POST | `/api/rooms/{id}/message` | Send message `{ "user_id": 1, "content": "Hello" }` |
| POST | `/api/rooms/{id}/schedule` | Schedule message (delayed job) `{ "user_id": 1, "content": "Later", "delay_minutes": 1 }` |
| GET | `/api/rooms/{id}/history` | Room message history |
| GET | `/api/analytics/room/{id}` | Room analytics (message_count) |

## Artisan Commands

### Package (laravel-nats)

- `php artisan nats:work` — NATS queue worker (moderation, analytics, notifications, delayed jobs)
- `php artisan nats:consume "{subject}" --handler=ClassName` — Subject consumer with handler (used for moderation and RPC in this PoC)

### This project

- `php artisan nats-chat:analytics-worker` — JetStream durable consumer for analytics
- `php artisan nats-chat:failed-jobs` — List failed NATS jobs

## Subject Structure

| Subject | Purpose |
|--------|---------|
| `chat.room.{roomId}.message` | Chat message (published on send) |
| `chat.room.*.message` | Moderation consumer (wildcard) |
| `chat.room.>` | JetStream stream + analytics |
| `user.rpc.preferences` | RPC: user notification preferences |

## Testing

- **Manual (API):** See **[docs/TESTING.md](docs/TESTING.md)** for curl examples and demo scenarios (happy path, failed jobs, delayed messages).
- **Quick script:** `./test-manual.sh` (or `BASE_URL=http://localhost:8090 ./test-manual.sh`).
- **Automated:** `composer test` or `docker compose exec app php artisan test` (requires PHP with pdo_sqlite).

## Documentation

- **[docs/DOCUMENTATION.md](docs/DOCUMENTATION.md)** — Full documentation: architecture, API reference, demo scenarios, local setup, troubleshooting, and package feature mapping.

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
