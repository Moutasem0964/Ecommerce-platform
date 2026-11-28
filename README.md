# E-Commerce Platform

Internet Applications course project — Damascus University, Faculty of Informatics Engineering.

A small e-commerce platform built like a real distributed system: a Laravel main app and a
Spring Boot inventory service that talk over gRPC, each with its own database, all in Docker.

## Architecture

```
                                                         writes ┌──► mysql (primary)
                                                                │       │ GTID replication
  Browser ──HTTP :8000──► web (nginx) ──FastCGI──► app (Laravel) ┤       ▼
                                                    │    │       └──► mysql-replica (reads)
                                                    │    └──► redis (cache, rate limits)
                                                    │ gRPC (internal only)
                                                    ▼
                                              inventory (Spring Boot) ──► postgres
```

| Service     | What it is                          | Reachable from your laptop |
|-------------|-------------------------------------|----------------------------|
| `web`       | nginx 1.28, reverse proxy           | `http://localhost:8000`    |
| `app`       | Laravel 13 on PHP 8.4-FPM           | no (behind nginx)          |
| `inventory` | Spring Boot 4.1, Java 21            | no (internal only)         |
| `mysql`     | MySQL 8.4 — main app data           | `localhost:13306`          |
| `postgres`  | PostgreSQL 17 — inventory data      | `localhost:5433`           |
| `mysql-replica` | MySQL 8.4 read replica          | `localhost:23306`          |
| `redis`     | Redis 7.4 — cache and rate limits   | no                         |
| `scheduler` | Laravel scheduler (saga cleanup)    | no                         |
| `health-monitor` | Replica lag + Redis watchdog   | no                         |
| `rabbitmq`  | RabbitMQ 4.1 — order events         | management UI `http://localhost:15672` |
| `worker`    | Sends order confirmation emails     | no                         |
| `queue`     | Laravel queue worker (broadcasts)   | no                         |
| `outbox-relay` | Publishes the outbox to RabbitMQ | no                         |
| `reverb`    | Laravel Reverb WebSocket server     | `ws://localhost:8000/app` (through nginx) |
| `swagger`   | Swagger UI for the API docs         | `http://localhost:8081`    |

## Repository layout

```
app/          Laravel main application
inventory/    Spring Boot inventory service
proto/        gRPC contract shared by both services
docker/       Dockerfiles, nginx config, replica scripts
k6/           Load tests, results and the before/after compare
compose.yaml  Starts the whole platform
.env.example  Ports and database credentials for compose
```

## Prerequisites

Only **Git** and **Docker Desktop** (WSL2 backend on Windows).
You do **not** need PHP, Composer, Java or Maven installed — they all run inside containers.

## First-time setup

Run from the repo root in Windows PowerShell:

```powershell
git clone https://github.com/Moutasem-Qassas/Ecommerce-platform.git
cd Ecommerce-platform
.\ci.ps1
```

`ci.ps1` does everything on a fresh clone and on every later run:

1. Creates `.env` and `app\.env` from the examples if missing, and generates `APP_KEY`.
2. Builds the images (Laravel code is baked into the image, like production) and starts the stack.
3. Runs migrations, generates the JWT key pair (`app/storage/keys`, git-ignored) and seeds the demo users.
4. Runs the architecture tests (module boundaries) and the API smoke tests, then prints response times.

If PowerShell refuses to run scripts: `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.

> **The first build takes 20–40 minutes.** The PHP gRPC extension is compiled from source and
> Maven downloads all Java dependencies. Both are cached afterwards; later builds take seconds.
> Avoid `docker builder prune`: it throws that cache away. Use `docker image prune -f` to free space.

Demo accounts (password `secret123`): `admin@test.com` (admin), `sara@test.com`, `hacker@test.com`.

## Check that it works

- **http://localhost:8000**: the web UI
- **http://localhost:8000/api/v1/ping**: `{"status":"ok"}`
- **http://localhost:8081**: Swagger UI for `docs/openapi.yaml`
- Postman: import `docs/postman_collection.json`

## Everyday commands

| Task | Command |
|------|---------|
| After every change (build, migrate, test) | `.\ci.ps1` |
| Start / stop (data is kept) | `docker compose up -d` / `docker compose down` |
| Follow logs of a service | `docker compose logs -f app` |
| Run an artisan command | `docker compose exec app php artisan ...` |
| Generate files (`make:...`) into your folder | `docker compose run --rm -v "${PWD}/app:/var/www/html" app php artisan make:...` |
| Add a PHP package | `docker compose run --rm -v "${PWD}/app:/var/www/html" app composer require vendor/package`, then `.\ci.ps1` |
| Reset the app database | `docker compose exec app php artisan migrate:fresh --seed` |
| Wipe all data and start fresh | `docker compose down -v`, then `.\ci.ps1` |

- The code is **inside the image**: a change shows up only after `.\ci.ps1` (or `docker compose up -d --build`).
- Use `composer install` (exact locked versions). Only run `composer update` on purpose, in its own PR.

## Laravel modules (modular monolith)

The Laravel app is a **modular monolith**: `app/app/Modules/{Shared, Identity, Inventory, Catalog, Ordering, Notifications}`.

- Each module owns its routes, migrations, config and tables, and registers itself through its own service provider.
- Inside a module: `Http/Controllers` (HTTP only) → `Http/Requests` (validation) → `Services` (business logic) → `Models`; `Http/Resources` shape the JSON; `Data` holds DTOs; `Exceptions` map to HTTP errors.
- Modules talk through contracts (`Catalog\Contracts\CatalogApi`) and events (`Ordering\Events\OrderStatusChanged`), never through each other's models or tables. There are no foreign keys across modules.
- Only `Inventory` talks gRPC to the Spring Boot service.
- `app/tests/Architecture` enforces all of this and runs in `ci.ps1`.

## Performance and caching (R3)

### What is in place

| Layer | What it does | Where |
|---|---|---|
| Redis cache | Product details and product list pages, TTL 10 min. Stock, order prices and per-user data are never cached. | `Catalog/Services/ProductCache` |
| Invalidation | After a product write commits: delete `catalog:product:{id}` and bump `catalog:list-version` (all list keys contain the version, old ones expire by TTL). | `ProductService::create/update` |
| ETag | `ETag` on product reads + `Cache-Control: no-cache`; same `If-None-Match` → `304`, no body. | `Shared/Http/Middleware/ETag` |
| Rate limiter on Redis | `INCR` instead of a MySQL transaction + row lock (was 78% of DB time in the baseline). | `CACHE_STORE` |
| Read replica | Reads go to `mysql-replica`, writes to `mysql`. | `config/database.php` |
| Read-your-writes | After a user's write, that user's reads go to the primary for 5 s. | `Shared/Http/Middleware/ReadYourWrites` |
| Replica health | `health-monitor` writes a heartbeat to the primary every second and reads it from the replica. Lag over 2 s, replica down or status unknown → all reads go to the primary. | `Shared/Services/ReplicaHealth` |
| Redis failure | Cache store `failover` = Redis, else per-request memory, with a 5 s circuit breaker. The site stays up (slower); rate limiting fails open; replica status becomes unknown → primary. After Redis comes back (and on monitor start) the cache is flushed twice, so writes made during the outage are never hidden by old entries. | `Shared/Cache`, `Shared/Services/CacheRecovery` |
| Login limit in nginx | 30 attempts per minute per IP on `/api/v1/auth/login`, independent of Redis and PHP. | `docker/nginx/default.conf` |
| Indexes | Covering index for a user's orders (`orders.user_id`); category index for filtered lists. | migrations |

### Diagnostic headers (add them as columns in DevTools → Network)

| Header | Values |
|---|---|
| `X-Cache` | `HIT` (Redis), `MISS` (loaded from DB and cached), `BYPASS` (cache off) |
| `X-Read-From` | Measured per request: `none`, `primary`, `replica`, `primary+replica` |
| `X-Replica` | `healthy (lag)`, `lagging (lag)`, `down`, `unknown`, `disabled` |
| `ETag` | Body hash; reload with the same value → `304` |

### Switches (`app/.env`)

| Variable | Before | After |
|---|---|---|
| `CACHE_STORE` | `database` | `failover` |
| `CATALOG_CACHE_ENABLED` | `false` | `true` |
| `HTTP_ETAG_ENABLED` | `false` | `true` |
| `DB_READ_HOST` | `mysql` | `mysql-replica` |

Also: `CATALOG_CACHE_TTL` (600), `READ_YOUR_WRITES_SECONDS` (5), `REPLICA_MAX_LAG_SECONDS` (2),
`CACHE_BREAKER_SECONDS` (5). Apply a change with:

```powershell
docker compose up -d --force-recreate app scheduler health-monitor
```

### Load test (k6) before / after

All k6 traffic comes from one IP, so set `API_RATE_LIMIT=1000000` in `app/.env` first (back to `60` afterwards).
The test data (5,000 users, 10,000 products, 200,000 orders) is created with
`docker compose exec app php artisan db:seed --class=LoadTestSeeder --force`.

```powershell
# "before" switches, recreate, then:
.\k6\run-load.ps1 -Label before -Quick       # 1 minute at 50 virtual users
# "after" switches, recreate, then (run twice, the first warms the cache):
.\k6\run-load.ps1 -Label after -Quick
.\k6\compare.ps1 -Before before -After after
```

Without `-Quick` the test ramps 20 → 50 users over 3.5 minutes. Results are saved in `k6/results/<label>.json`.

Measured on one laptop (ms, 1 minute at 50 users, warm):

| endpoint | p50 | p95 | p99 |
|---|---|---|---|
| products page | 49.3 → 10.3 | 207.0 → 22.5 | 348.2 → 53.8 |
| products by category | 44.0 → 10.3 | 200.9 → 22.5 | 355.0 → 48.6 |
| product details | 42.4 → 10.2 | 194.6 → 21.3 | 328.2 → 44.6 |
| my orders | 53.7 → 18.7 | 207.7 → 38.2 | 334.3 → 70.7 |
| **all** | **46.7 → 10.7** | **204.7 → 26.5** | **343.8 → 58.2** |

Throughput 78 → 95 req/s. k6 here is a closed model (each user waits for the answer, then sleeps),
so ~95 req/s is the most 50 users can ask for: before, the server could not keep up; after, it can.

### Useful commands during a demo

| Task | Command |
|---|---|
| Flush the cache | `docker compose exec redis redis-cli FLUSHALL` |
| Look at cache keys | `docker compose exec redis redis-cli`, then `SELECT 1` and `KEYS *` |
| MySQL shell (primary) | `docker compose exec mysql sh -c 'mysql -uroot -p$MYSQL_ROOT_PASSWORD'`, then `USE ecommerce;` |
| Make the replica lag | on `mysql-replica`: `STOP REPLICA SQL_THREAD;` … `START REPLICA SQL_THREAD;` |
| Hide / show an index | `ALTER TABLE orders ALTER INDEX orders_user_id_index INVISIBLE;` (`VISIBLE` to restore) |
| Stop Redis / the replica | `docker compose stop redis` / `docker compose stop mysql-replica` (`start` to restore) |
| Cut Redis off the network | `docker network disconnect ecommerce-platform_backend ecommerce-platform-redis-1`, restore with `docker network connect --alias redis ecommerce-platform_backend ecommerce-platform-redis-1` |
| Monitor log | `docker compose logs health-monitor --tail 20` |

## Asynchronous queues and real-time updates (R4)

Two things happen in the background so the customer never waits for them: the confirmation email
(RabbitMQ + a worker) and the live status updates (WebSocket through Laravel Reverb).

```
POST /orders ─► OrderPlaced ─► queue worker ─► Reverb ─┬─► admin.orders   (admin only)
      │                                                └─► orders.{id}    (owner or admin)
      └─ stock reserved ─► one transaction: status = confirmed + row in outbox_messages
                                                │
                              outbox-relay (every 0.5 s) ─► RabbitMQ  orders / order.created
                                                │
                                   notifications.order-confirmation ─► worker ─► SMTP (Mailtrap) ─► processed_messages ─► ack
                                                │ failure
                                   ...delay queue (per-message expiration 10 s) ─► back to the main queue
                                                │ after 5 attempts
                                   notifications.order-confirmation.dlq   (never dropped)
```

### What is in place

| Piece | What it does | Where |
|---|---|---|
| Event publishing (outbox) | After the stock is reserved, the status change to `confirmed` and the message row in `outbox_messages` are written in **one transaction**: both or neither. The `outbox-relay` container publishes pending rows (persistent, publisher confirm) and marks them published. If RabbitMQ is down, rows wait and go out when it is back. A rejected or failed order writes no message. | `Ordering/Services/OrderService`, `Notifications/Listeners/PublishOrderConfirmation`, `Shared/Messaging/Outbox`, `Shared/Messaging/OutboxRelay` |
| Worker | The `worker` container takes one message at a time and sends the email through an external SMTP service (Mailtrap). It records the message id in `processed_messages` and only then acknowledges. A message whose id is already there is acknowledged without sending, so a redelivery never sends a second email. `docker compose stop worker` finishes the current message and exits cleanly. | `Notifications/Console/ConsumeOrderConfirmations` |
| No lost messages | Durable queues, persistent messages, manual ack. If the worker is stopped or crashes, the messages stay in the queue and are delivered when it starts again. | `RabbitMq::declareTopology` |
| Retries | A failure is republished (with publisher confirm) to the delay queue with a per-message expiration, then acknowledged; when it expires it returns to the main queue. The attempt number travels in the message header `x-attempts`. Because the delay lives on the message, changing `ORDER_EMAIL_RETRY_TTL_MS` needs no queue changes. | `ConsumeOrderConfirmations`, `RabbitMq::declareTopology` |
| Dead-letter queue | After 5 attempts the message is copied (with publisher confirm) to its own queue, `notifications.order-confirmation.dlq`, and only then acknowledged. `php artisan notifications:replay-dlq` puts every parked message back. | `ConsumeOrderConfirmations`, `ReplayDeadLetters` |
| Live updates | `OrderPlaced` (`order.placed`) and `OrderStatusChanged` (`order.status-changed`) are broadcast by the `queue` container to Reverb, which nginx exposes on `/app/`. In the main UI, after login, a customer follows the orders in "طلباتي" and an admin follows `admin.orders`: a toast appears and the list and the open order refresh without reloading the page. | `Ordering/Events`, `resources/views/welcome.blade.php`, `docker/nginx/default.conf` |
| Authorization | `orders.{id}`: the order's owner or an admin. `admin.orders`: admins only. Both are checked on `POST /broadcasting/auth` with the same JWT as the API. | `routes/channels.php` |
| Debug pages | `http://localhost:8000/admin.html` and `/tracking.html` show the raw events. Paste a JWT, press the button, wait for "subscribed". | `app/public` |

### Switches (`app/.env`)

| Variable | Default | Meaning |
|---|---|---|
| `ORDER_EMAIL_FAIL_SENDING` | `false` | DEMO ONLY: make every send fail, to show the retries and the DLQ |
| `ORDER_EMAIL_RETRY_TTL_MS` | `10000` | Wait between two attempts |
| `ORDER_EMAIL_MAX_ATTEMPTS` | `5` | Attempts before the message goes to the DLQ |
| `OUTBOX_POLL_MS` | `500` | How often the relay looks for pending outbox rows |
| `MAIL_*` | Mailtrap | Credentials of the external SMTP service |

Apply a change with `docker compose up -d --force-recreate worker outbox-relay`.

### Demo: the four proofs

Log in as `admin@test.com` / `sara@test.com` (password `secret123`) and keep the token in `$admin` / `$sara`.

| Proof | How |
|---|---|
| A new order shows up on the admin dashboard immediately | Two browser windows (one private): the admin on "طلباتي" (all orders), Sara buys a product. The admin gets a toast and the order appears in the list. |
| The admin's approval shows up on the customer's page immediately | Sara on "طلباتي" (or with the order details open), the admin ships the order. Sara gets a toast and the status changes in place. |
| Stopping the worker loses no email | `docker compose stop worker`, place 3 orders, `docker compose exec rabbitmq rabbitmqctl list_queues name messages_ready` shows 3 ready, then `docker compose start worker`: 3 emails are sent. |
| Retries and the DLQ | Set `ORDER_EMAIL_FAIL_SENDING=true`, recreate `worker`, place an order: `attempt 1/5` … `5/5`, then `parked in DLQ` (RabbitMQ UI: `notifications.order-confirmation.dlq` = 1). Set it back to `false` and run `docker compose exec worker php artisan notifications:replay-dlq`. |

| RabbitMQ down loses no email (outbox) | `docker compose stop rabbitmq`, place an order (it succeeds), `docker compose logs outbox-relay` shows `cannot publish yet`, then `docker compose start rabbitmq`: the relay publishes it and the email is sent. |

Access control: `tracking.html` with another customer's token, or `admin.html` with a customer token, shows "subscription rejected". `ci.ps1` checks it on `/broadcasting/auth` (owner 200, other customer 403, customer on `admin.orders` 403, no token 401), and checks that each order's message reaches the outbox, RabbitMQ and the worker.

### Delivery guarantees and limits

- **At least once, without duplicates in practice.** The relay may publish a row twice (it crashed after publishing, before marking it) and RabbitMQ may redeliver; `processed_messages` makes the worker skip both. The one window left is a crash between the SMTP send and writing `processed_messages`.
- **Outbox.** A confirmed order always has its message, even if RabbitMQ is down at that moment; it leaves when the broker is back. Published rows are deleted after a day.
- Mailtrap's free plan limits emails per second; the retries absorb that (several orders placed together may need 2 or 3 attempts).

## Connecting a database tool (DBeaver, IntelliJ, TablePlus)

| | MySQL (app) | PostgreSQL (inventory) |
|---|---|---|
| Host | `localhost` | `localhost` |
| Port | `13306` (replica `23306`) | `5433` |
| Database | `ecommerce` | `inventory` |
| User / password | from your `.env` | from your `.env` |

## Troubleshooting

**`Ports are not available ... forbidden by its access permissions`**
Windows (Hyper-V / WSL2) has reserved that port. List the reserved ranges:

```powershell
netsh interface ipv4 show excludedportrange protocol=tcp
```

Pick ports outside those ranges and change `APP_PORT`, `MYSQL_PORT` or `POSTGRES_PORT` in your
root `.env`. Nothing else needs to change.

**`service "app" is not running`**
Start the stack first with `docker compose up -d`, then run `exec` commands.

**`/usr/bin/env: 'sh\r': No such file or directory` when building inventory**
`mvnw` got Windows line endings. Run `git config --global core.autocrlf input`, delete
`inventory/mvnw`, and run `git checkout -- inventory/mvnw`.

## gRPC contract (`proto/`)

The contract between the two services lives in `proto/inventory/v1/*.proto`. Both sides are
generated from it — nobody writes request/response classes by hand.

| Side | How its code is generated |
|------|---------------------------|
| Spring Boot (server) | Automatically by the Maven build (`protobuf-maven-plugin` reads `../proto`) |
| Laravel (client) | `docker compose run --rm buf generate` → `app/generated/` (committed to Git) |

After changing a `.proto` file:

```powershell
docker compose run --rm buf lint                # style rules
docker compose run --rm buf generate            # regenerate PHP client code
docker compose up -d --build inventory          # regenerate + rebuild the Java side
```

PHP generated classes use the `Proto\` namespace (set `php_namespace` / `php_metadata_namespace`
in the `.proto` file to `Proto\Inventory\V1` and `Proto\GPBMetadata\Inventory\V1`).

## Contributing

`main` is protected: every change goes through a pull request with one teammate's approval and
is squash-merged.

```powershell
git switch main
git pull
git switch -c feat/app-orders-create      # one task = one branch
# ...work, commit...
git push -u origin feat/app-orders-create # then open a PR on GitHub
```

- Branch / PR titles show the area: `feat(app): ...`, `feat(inventory): ...`, `feat(proto): ...`, `chore(infra): ...`
- Never commit `.env`, `vendor/` or `target/`.
- gRPC contract changes: only add fields; never renumber or reuse a field number.