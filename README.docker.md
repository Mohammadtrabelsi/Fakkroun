# Docker Development Environment

A containerised local development stack for the MajidaStarter Laravel application. The Laravel project lives in `src/`, and Docker provides everything around it: PHP-FPM, Nginx, MySQL, phpMyAdmin, and Redis. Nothing needs to be installed on your host except Docker and Docker Compose.

> **Heads-up:** the Docker stack mounts `./src` as the application root. In this repository the Laravel app sits at the top level, so the first-time setup below copies it into `src/`. Node.js is not in the PHP image, so front-end assets are built in a separate Node container.

---

## Folder structure

```
.
├── docker-compose.yml        # Defines all services (app, web, db, phpmyadmin, redis)
├── .env.docker.example       # Sample env for the Docker stack -> copy to .env
├── .env                      # Your local Docker settings (git-ignored, you create it)
├── README.docker.md          # This file
├── docker/
│   ├── php/
│   │   ├── Dockerfile        # PHP 8.4-FPM image with Laravel extensions + Composer
│   │   └── php.ini           # Custom PHP settings for development
│   └── nginx/
│       └── default.conf      # Nginx vhost pointing at src/public
└── src/                      # <-- The Laravel application (mounted into the containers)
    ├── public/               # Web root (index.php)
    ├── .env                  # Laravel's OWN env (DB_HOST=db lives here)
    └── ...
```

> Two `.env` files, two different jobs:
> - **`./.env`** configures the **Docker stack** (ports, MySQL credentials, UID/GID).
> - **`./src/.env`** configures **Laravel itself** (its DB connection, app key, etc.).

---

## First-time setup

### 1. Copy the Docker environment file

```bash
cp .env.docker.example .env
```

Edit `.env` if any of the default host ports (`8080`, `8081`, `3306`, `6379`) are already in use on your machine.

### 2. Copy the Laravel application into `src/`

Run this from the repository root. It copies the application files and leaves the Docker files where they are:

```bash
cp -R app bootstrap config database lang public resources routes storage tests \
      artisan composer.json composer.lock package.json package-lock.json \
      vite.config.js phpunit.xml phpstan.neon.dist .env.example src/
```

After this, `src/public/index.php` and `src/artisan` exist. Do not run `composer create-project` here: that would scaffold a new, unrelated Laravel app.

### 3. Point Laravel at the `db` container

Create Laravel's own env file:

```bash
cp src/.env.example src/.env
```

Then edit `src/.env` so the database and Redis values read:

```dotenv
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

# Optional: use Redis for cache and sessions instead of the database
# REDIS_HOST=redis
# CACHE_STORE=redis
# SESSION_DRIVER=redis
```

The `DB_*` values must match those in the root `.env` (they seed the MySQL container). `DB_HOST=db` is what lets Laravel reach MySQL over the Docker network. The Redis service is available as `redis`, but the default configuration does not use it.

### 4. Build and start the stack

```bash
docker compose up -d --build
```

### 5. Install PHP dependencies

```bash
docker compose exec app composer install
```

### 6. Build the front-end assets

The `app` container has no Node.js, so build assets in a throwaway Node container. Node 20 matches CI:

```bash
docker run --rm -v "$(pwd)/src":/app -w /app node:20-alpine sh -c "npm ci && npm run build"
```

### 7. Run migrations and link storage

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
```

`storage:link` is needed for post cover images. Add `--seed` to `migrate` if you want the demo accounts (see the root README for the list).

### 8. Start the background workers

Mail is queued by default (`QUEUE_CONNECTION=database`), and the scheduler prunes the activity log and rebuilds `public/sitemap.xml`. Neither runs inside the stack automatically, so start them in the background:

```bash
docker compose exec -d app php artisan queue:work
docker compose exec -d app php artisan schedule:work
```

You're done — open the app in your browser.

---

## URLs

| Service      | URL                     | Notes                                   |
|--------------|-------------------------|-----------------------------------------|
| Application  | http://localhost:8080   | Served by Nginx (`APP_PORT`)            |
| Health check | http://localhost:8080/up | Liveness endpoint used by the admin health card |
| phpMyAdmin   | http://localhost:8081   | Log in with the MySQL user (`PMA_PORT`) |

phpMyAdmin login: server `db`, user `laravel`, password `secret` (or your root user `root` / `root`), matching the values in your `.env`.

---

## Common Docker Compose commands

```bash
# Start everything in the background (rebuild images if needed)
docker compose up -d --build

# Stop the stack (keeps the database volume)
docker compose down

# Stop and DELETE the database volume (fresh DB next time)
docker compose down -v

# View logs (all services, or one)
docker compose logs -f
docker compose logs -f app

# List running services
docker compose ps

# Open a shell in the PHP container
docker compose exec app bash

# Run artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker
docker compose exec app php artisan test

# Composer inside the container
docker compose exec app composer install
docker compose exec app composer require vendor/package

# Front-end build (run from the repository root, same as step 6)
docker run --rm -v "$(pwd)/src":/app -w /app node:20-alpine sh -c "npm ci && npm run build"

# Rebuild just the PHP image after changing the Dockerfile
docker compose build app

# Restart a single service
docker compose restart web
```

---

## Services at a glance

| Service      | Image                | Purpose                                  |
|--------------|----------------------|------------------------------------------|
| `app`        | custom PHP 8.4-FPM   | Runs Laravel (PHP + Composer)            |
| `web`        | nginx:1.27-alpine    | Web server, proxies PHP to `app`         |
| `db`         | mysql:8.0            | Database (Laravel connects via `db`)     |
| `phpmyadmin` | phpmyadmin:5         | Database web UI                          |
| `redis`      | redis:7-alpine      | Optional cache / session store (not used by default) |

The `app` image includes the `redis` PHP extension, so Redis works once `REDIS_HOST` and the drivers are set in `src/.env`.
