# Plan: Phase 0 — Docker & Environment Setup (Tasks 0.4–0.8)

## Context
Symfony scaffolded a minimal `compose.yaml` (only a `database` service) and `compose.override.yaml` (ports + mailpit). Tasks 0.4–0.8 require a full four-service Docker setup. The user wants:
- `compose.yaml` rewritten entirely (not extended)
- PostgreSQL 17 (18 has no stable image)
- `.env.local` written directly (not a dist template)

---

## Files to Create / Modify

| File | Action |
|---|---|
| `Dockerfile` | Create — PHP 8.3-cli image for `app` + `worker` |
| `Dockerfile.frontend` | Create — Node 20 image for `frontend` |
| `compose.yaml` | Rewrite — four services |
| `compose.override.yaml` | Clear — old `database`+mailpit refs conflict with rewrite |
| `.env.local` | Create — local dev overrides |
| `TASKS.md` | Check off 0.4, 0.5, 0.6, 0.7 (0.8 is a manual verify step) |

---

## Implementation Details

### `Dockerfile` (app + worker)
```dockerfile
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libzip-dev libicu-dev \
  && docker-php-ext-install pdo_pgsql intl zip pcntl opcache \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
  && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /app
COPY . .
RUN composer install --no-interaction --prefer-dist

EXPOSE 8000
CMD ["symfony", "server:start", "--no-tls", "--allow-http"]
```

### `Dockerfile.frontend`
```dockerfile
FROM node:20-alpine

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY . .

EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host"]
```

### `compose.yaml` (full rewrite)
```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - .:/app
    ports:
      - "8000:8000"
    depends_on:
      db:
        condition: service_healthy
    env_file: .env.local

  frontend:
    build:
      context: ./frontend
      dockerfile: ../Dockerfile.frontend
    volumes:
      - ./frontend:/app
      - /app/node_modules
    ports:
      - "5173:5173"

  db:
    image: postgres:17
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-webhookapp}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-secret}
      POSTGRES_USER: ${POSTGRES_USER:-app}
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "${POSTGRES_DB:-webhookapp}", "-U", "${POSTGRES_USER:-app}"]
      timeout: 5s
      retries: 5
      start_period: 60s
    volumes:
      - database_data:/var/lib/postgresql/data:rw
    ports:
      - "5432:5432"

  worker:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - .:/app
    command: php bin/console messenger:consume async --time-limit=3600
    depends_on:
      db:
        condition: service_healthy
    env_file: .env.local

volumes:
  database_data:
```

### `compose.override.yaml` (clear it)
Replace with an empty services block to avoid conflicts with the removed `database` and `mailer` services.

### `.env.local`
```
APP_ENV=dev
APP_SECRET=change_me_in_production
DATABASE_URL="postgresql://app:secret@db:5432/webhookapp?serverVersion=17&charset=utf8"
```
Note: `@db` hostname matches the `db` service name in compose.yaml.

---

## Task 0.8 — Verification
Manual step after implementation:
```bash
docker compose build
docker compose up
```
Confirm:
- `app` serves on http://localhost:8000
- `frontend` (Vite HMR) serves on http://localhost:5173
- `db` passes health check
- `worker` starts and waits for messages

---

## TASKS.md Updates
Mark 0.4, 0.5, 0.6, 0.7 as `[x]`. Task 0.8 stays `[ ]` — user must verify Docker startup manually.
