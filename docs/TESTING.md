# How to Test NATS Chat PoC

Use the **web UI** for manual testing (recommended). Optionally run **PHPUnit** for automated tests.

---

## Prerequisites

- Docker stack running: `docker compose up -d`
- Open in browser: **http://localhost:8090**

---

## 1. Web UI — What to provide for each action

All testing is done at **http://localhost:8090**. The UI has a **sidebar** (rooms list + create) and a **main area** (selected room: history, analytics, send/schedule forms).

### Create a room

| Input | What to provide | Example |
|-------|------------------|---------|
| **New room name** | Any non-empty name (required) | `General`, `Demo`, `Support` |

- Leave the field **empty** and click Create to see a **validation error** (name required).
- After creating, the new room appears in the sidebar; click it to open.

---

### Send a message

Select a room first. Then:

| Input | What to provide | Example |
|-------|------------------|---------|
| **User ID** | Integer ≥ 1 (required) | `1`, `42` |
| **Message content** | Any non-empty text (required, max 10000 chars) | `Hello`, `Test message` |

- Click **Send** to publish immediately. The message appears in **Room history** after you click **Refresh**.
- Leave **content** empty and click Send to see a **validation error**.

---

### Schedule a message (delayed)

Select a room first. Then:

| Input | What to provide | Example |
|-------|------------------|---------|
| **User ID** | Integer ≥ 1 (required) | `1` |
| **Message content** | Any non-empty text (required) | `This will appear in 2 minutes` |
| **Delay (minutes)** | Integer 1–1440 (optional; default 1) | `1`, `2`, `5` |

- Click **Schedule**. The message is queued and will be sent after the given delay. After that time, click **Refresh** to see it in history.
- **Note:** Delayed delivery requires NATS JetStream and the queue connector to be available. If scheduling fails, check the error message in the UI and ensure NATS is running with JetStream (`-js`).

---

### Send fail-test message

- **No inputs** — click the red **Send fail-test message** button.
- This sends a fixed message that causes moderation/notification/analytics jobs to **throw**, retry, and then land in `failed_jobs`.
- After running, list failed jobs in the CLI:  
  `docker compose exec app php artisan nats-chat:failed-jobs`

---

### Refresh (history and analytics)

- **No inputs** — click **Refresh** in the room header.
- Reloads **Room history** (messages) and **Message count** (analytics). Use this after sending messages or waiting for a scheduled message so the UI shows the latest data.

---

## 2. Suggested test flow (UI)

1. **Create a room**  
   - Input: name e.g. `Demo` → Create.  
   - Click the new room in the sidebar.

2. **Send a message**  
   - User ID: `1`, content: `Hello` → Send.  
   - Click **Refresh** → message appears in history; **Message count** may increase after workers run.

3. **Schedule a message**  
   - User ID: `1`, content: `Delayed hello`, delay: `1` → Schedule.  
   - Wait ~1 minute, click **Refresh** → scheduled message appears in history.

4. **Validation**  
   - Create room with empty name → error.  
   - Send message with empty content → error.

5. **Failed jobs**  
   - Click **Send fail-test message**.  
   - Run: `docker compose exec app php artisan nats-chat:failed-jobs` → failed jobs listed.

---

## 3. Automated tests (PHPUnit)

Uses **SQLite** in-memory and **sync** queue (no NATS required).

**On host (PHP 8.2+ with pdo_sqlite):**

```bash
composer test
```

**In Docker:**

```bash
docker compose exec app php artisan test
```

**Coverage:**

- **RoomsApiTest:** list rooms (empty and with rooms), create room (success and name validation), room history (empty, with messages, 404 for missing room).
- **MessageApiTest:** send message (success, validation for user_id/content, user_id min:1, 404 for missing room), schedule message (success, validation for user_id/content and delay_minutes range, 404 for missing room).
- **AnalyticsApiTest:** get analytics for room (without/with analytic record, 404 for missing room).
- **DlqApiTest:** GET /api/dlq (empty list and with failed messages).
- **MetricsApiTest:** GET /api/metrics returns structure (messages_processed, messages_failed, retries_count, avg_processing_time_ms).

---

## 4. Dead Letter Queue (DLQ)

Failed queue jobs (after max retries) are published to **chat.dlq** and can be stored in the database.

1. **Bootstrap DLQ stream** (once): `php artisan nats-chat:dlq-bootstrap`
2. **Run DLQ store worker**: `php artisan nats-chat:dlq-store` (consumes from JetStream and writes to `failed_messages` table).
3. **List failed messages**: `GET /api/dlq` (paginated).

JetStream consumer retry is configured via `NATS_JETSTREAM_ACK_WAIT` and `NATS_JETSTREAM_MAX_DELIVER` (see `config/nats.php`).

---

## 5. Optional: curl / script

For CI or headless runs you can use the API with curl or the `./test-manual.sh` script in the project root.

---

## 6. Useful commands

| Check | Command |
|-------|---------|
| Containers | `docker compose ps` |
| App logs | `docker compose logs app --tail=30` |
| Queue logs | `docker compose logs queue --tail=30` |
| Failed jobs | `docker compose exec app php artisan nats-chat:failed-jobs` |
| NATS monitor | http://localhost:8224 |
| phpMyAdmin | http://localhost:8091 (server: mysql, user: nats_chat, password: secret) |
