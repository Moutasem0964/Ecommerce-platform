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
git clone https://github.com/Moutasem0964/Ecommerce-platform.git
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
