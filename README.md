# E-Commerce Platform

Internet Applications course project — Damascus University, Faculty of Informatics Engineering.

A small e-commerce platform built like a real distributed system: a Laravel main app and a
Spring Boot inventory service that talk over gRPC, each with its own database, all in Docker.

## Architecture

```
  Browser ──HTTP :8000──► web (nginx) ──FastCGI──► app (Laravel, PHP-FPM) ──► mysql
                                                        │
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
| `scheduler` | Laravel scheduler (saga cleanup)    | no                         |
| `swagger`   | Swagger UI for the API docs         | `http://localhost:8081`    |

## Repository layout

```
app/          Laravel main application
inventory/    Spring Boot inventory service
proto/        gRPC contract shared by both services
docker/       Dockerfiles and nginx config
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

## Connecting a database tool (DBeaver, IntelliJ, TablePlus)

| | MySQL (app) | PostgreSQL (inventory) |
|---|---|---|
| Host | `localhost` | `localhost` |
| Port | `13306` | `5433` |
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
