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

Run these from the repo root (Windows PowerShell shown; the same commands work in bash).

```powershell
# 1. Clone
git clone https://github.com/Moutasem0964/Ecommerce-platform.git
cd Ecommerce-platform

# 2. Settings files (never commit the .env files)
Copy-Item .env.example .env
Copy-Item app\.env.example app\.env

# 3. Build and start everything (first run is slow — see note below)
docker compose up -d --build

# 4. Install Laravel's PHP libraries (exact versions from composer.lock)
docker compose exec app composer install

# 5. Generate Laravel's encryption key
docker compose exec app php artisan key:generate

# 6. Let the web user write to Laravel's cache/log folders
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R ug+rwX storage bootstrap/cache

# 7. Create the database tables
docker compose exec app php artisan migrate
```

> **The first build takes 20–40 minutes.** The PHP gRPC extension is compiled from source and
> Maven downloads all Java dependencies. Both are cached afterwards — later builds take seconds.
> If you see `[output clipped, log limit 2MiB reached]`, the build is still running; don't cancel it.

## Check that it works

```powershell
docker compose ps
```

All five containers should be `Up`, and `mysql` / `postgres` should show `(healthy)`.

- Open **http://localhost:8000** — you should see the Laravel welcome page.
- Check the inventory service from inside the Docker network:

  ```powershell
  docker compose exec app curl -s http://inventory:8080/actuator/health
  ```

  Expected: `{"groups":["liveness","readiness"],"status":"UP"}`

## Everyday commands

| Task | Command |
|------|---------|
| Start everything | `docker compose up -d` |
| Stop everything (data is kept) | `docker compose down` |
| Follow logs of a service | `docker compose logs -f app` |
| Run an artisan command | `docker compose exec -u www-data app php artisan ...` |
| Add a PHP package | `docker compose exec app composer require vendor/package` |
| Rebuild inventory after Java changes | `docker compose up -d --build inventory` |
| Wipe all data and start fresh | `docker compose down -v` |

- Laravel code is mounted live: edit files in `app/` and refresh the browser — no rebuild needed.
- Run artisan with `-u www-data` so generated files are owned by the web user.
- Use `composer install` (exact locked versions). Only run `composer update` on purpose, in its own PR.

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

**Laravel shows `tempnam(): file created in the system's temporary directory`**
The web user cannot write to `storage/`. Re-run step 6 of the setup.

**`service "app" is not running`**
Start the stack first with `docker compose up -d`, then run `exec` commands.

**`/usr/bin/env: 'sh\r': No such file or directory` when building inventory**
`mvnw` got Windows line endings. Run `git config --global core.autocrlf input`, delete
`inventory/mvnw`, and run `git checkout -- inventory/mvnw`.

**Laravel feels slow on Windows**
Bind mounts from `C:\` are slower than from the WSL filesystem. For better speed, clone the
repo inside WSL (e.g. `~/projects`) and run Docker commands from there.

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
