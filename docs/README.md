Documentation for this PoC lives in the **[root README.md](../README.md)** (architecture, setup, NATS usage, **Failure Handling & Reliability**).

Quick links:

- **DLQ HTTP API:** `GET /api/dlq` (paginated `failed_messages`).
- **Replay:** `php artisan nats:dlq:replay` (`--dry-run`, `--limit`, `--id=`). Rows need **`source_subject`** (set when messages are moved to DLQ).
- **Tests:** `php artisan test tests/Feature/DlqApiTest.php`
