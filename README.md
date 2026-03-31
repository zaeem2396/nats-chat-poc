# Laravel + NATS JetStream — Distributed Order Pipeline (Production-Style PoC)

This repository is a **production-grade proof of concept**: a **multi-stage, event-driven order system** in a **single Laravel codebase**, wired like **separate microservices** through **NATS JetStream** and **[zaeem2396/laravel-nats](https://github.com/zaeem2396/laravel-nats)** (built on **[basis-company/nats.php](https://github.com/basis-company/nats.php)**).

It is **not** a toy chat demo. It demonstrates **durable consumers**, **explicit ACK / NACK**, **retry semantics**, **dead-letter handling with DB audit**, **idempotency**, **structured envelopes**, and **config-driven subjects** — patterns you would expect when explaining NATS to operators or maintainers.

---

## Architecture (logical microservices in one app)

| Logical service        | Responsibility |
|------------------------|----------------|
| **Order**              | HTTP `POST /api/orders` → persist `orders` row → **JetStream publish** `orders.created` (sync server ack). |
| **Orders worker**      | Durable pull consumer on `orders.created` → ingress: set `pipeline_stage`, **idempotent** duplicate handling, **ACK**. |
| **Payments worker**    | Durable pull consumer on `orders.created` → simulate PSP: **NACK** on transient failure (retry), **ACK** + publish `payments.completed` or `payments.failed`. |
| **Inventory worker**   | Durable pull consumer on `payments.completed` → deduct stock → publish `inventory.updated`, **NACK** if insufficient stock (retry). |
| **Notifications worker** | Durable pull consumer on `payments.completed` **and** `payments.failed` (multi-filter) → persist `order_notifications`, **idempotent** by event id, **ACK**. |

All inter-service traffic is **JetStream** (persisted, replayable), not Laravel queues.

---

## Event flow (text diagram)

```
[Client] --POST /orders--> [Order API + DB]
                              |
                              | NatsV2::jetStreamPublish (ORDERS stream, subject orders.created)
                              v
                    +-------------------+
                    | JetStream ORDERS  |
                    | orders.*          |
                    | payments.*        |
                    | inventory.*       |
                    +-------------------+
                         |          |
            orders.created          |
                    +-----+----+     |
                    |     |    |     |
                    v     v    |     |
            [Orders worker] [Payments worker]
                    |            |
                    |            +-- NACK (transient) ---> retry (same consumer)
                    |            +-- ACK + publish payments.completed / payments.failed
                    |                              |
                    |                              v
                    |                    +------------------+
                    |                    | notifications.*  |
                    |                    | (via filters)    |
                    |                    +------------------+
                    |                         ^        ^
                    |                         |        |
                    +-------------------------+    [Notifications worker]
                    |
            payments.completed
                    |
                    v
            [Inventory worker] --ACK--> publish inventory.updated

DLQ path (payments example):
  Max app-level attempts exceeded or poison message
    --> term() on JetStream msg
    --> publish wrapped payload to payments.dlq (still under ORDERS stream)
    --> insert row in failed_messages
```

---

## Why this is production-relevant

1. **JetStream** gives you **at-least-once delivery**, **replay**, and **consumer lag** visibility — the same primitives used in real event backbones.
2. **Durable consumers** survive process restarts; cursors live on the server.
3. **Explicit ACK / NACK** models success vs “try again” without silently dropping work.
4. **max_deliver** + **ack_wait** on consumers (see `config/nats_orders.php`) bound redelivery and stuck-work behavior.
5. **DLQ + `failed_messages`** gives operators an **audit trail** and a subject (`*.dlq`) for tooling/alerting.
6. **Idempotency** (cache keys per envelope id / consumer) shows how to handle **duplicate deliveries** safely.

---

## NATS usage in this PoC

| Mechanism | Where |
|-----------|--------|
| Stream **ORDERS** | Subjects `orders.*`, `payments.*`, `inventory.*` (includes `*.dlq`). |
| Publish | `NatsV2::jetStreamPublish()` — JSON envelope `{ id, type, version, data, idempotency_key? }` from **laravel-nats** (type mirrors subject, e.g. `orders.created`). |
| Pull consumers | `NatsV2::jetStreamPull()` in a long loop (`app/Services/Nats/JetStreamOrderWorkerLoop.php`). |
| Consumer creation | `OrderStreamConsumerProvisioner` sets **filter_subject** / **filter_subjects**, **explicit ack**, **ack_wait**, **max_deliver**. |
| ACK / NACK / term | `Basis\Nats\Message\Msg` — `ack()`, `nack(delay)`, `term(reason)`. |

---

## Configuration

- **`config/nats_orders.php`** — stream name, subjects, durable consumer names, retry/DLQ tuning, pull batch/expiry.
- **`config/nats_basis.php`** — merged from the package; preset `order_processing` is **injected at boot** via `OrderMessagingServiceProvider`.

Environment highlights (see `.env.example`):

- `NATS_HOST`, `NATS_PORT` — broker for **NatsV2** / JetStream.
- `NATS_ORDER_MAX_ATTEMPTS` — app-level payment attempts before DLQ.
- `NATS_PAYMENT_TRANSIENT_FAIL_PERCENT`, `NATS_PAYMENT_TERMINAL_FAIL_PERCENT` — simulation knobs.

---

## Step-by-step setup (Docker)

**Prerequisites:** Docker Compose, ports **8090** (app), **3307** (MySQL), **4223/8224** (NATS client/monitor) free (or override with env vars in compose).

```bash
cp .env.example .env   # ensure DB_* and NATS_* match docker-compose
docker compose up -d --build

docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app php artisan nats:orders:provision-stream
```

**Workers** (Compose services):

- `orders-worker` → `php artisan nats:orders-worker`
- `payments-worker` → `php artisan nats:payments-worker`
- `inventory-worker` → `php artisan nats:inventory-worker`
- `notifications-worker` → `php artisan nats:notifications-worker`

**Smoke test**

```bash
curl -sS -X POST http://localhost:8090/api/orders \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"sku":"SKU-DEMO","quantity":2,"total_cents":1999}'

curl -sS http://localhost:8090/api/orders
curl -sS http://localhost:8090/api/metrics
curl -sS http://localhost:8090/api/dlq
```

Watch logs: `docker compose logs -f payments-worker` (transient **NACK** / retries), `notifications-worker` (completed/failed), `inventory-worker` (stock).

**CLI health**

```bash
docker compose exec app php artisan nats:ping
docker compose exec app php artisan nats:v2:jetstream:streams
```

---

## HTTP API

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/health` | App + `NatsV2::ping` |
| POST | `/api/orders` | Create order + publish `orders.created` |
| GET | `/api/orders` | List recent orders |
| GET | `/api/orders/{id}` | Order + payments |
| GET | `/api/metrics` | Counts (orders, payments, notifications, DLQ rows) |
| GET | `/api/dlq` | Paginated `failed_messages` |

---

## Project layout (order pipeline)

```
app/Consumers/          # JetStream pull handlers (ACK/NACK/term)
app/Services/           # OrderService, PaymentService, InventoryService, NotificationService
app/Services/Nats/      # Provisioner, worker loop, DLQ helper
app/Support/            # OrderPipelineEventParser
config/nats_orders.php
```

---

## Testing

```bash
composer test
# or
php artisan test
```

Feature tests mock `nats.v2` for `POST /api/orders` so CI does not require a live broker. Full pipeline validation is intended via Docker + workers + `curl` as above.

---

## License

MIT (same as Laravel skeleton unless otherwise noted).
