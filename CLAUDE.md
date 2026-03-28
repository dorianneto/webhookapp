# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Webhook-as-a-Service (WaaS) MVP — a platform that receives webhooks from third-party services and fans them out to user-defined destination URLs with automatic retries and a delivery dashboard.

**Stack:** Symfony 7 (PHP 8.3+) backend + React 18 + Vite frontend, monolith deployment, PostgreSQL 18, Symfony Messenger with Doctrine transport.

## Development Commands

### Full stack (Docker Compose)
```bash
docker compose up              # Start all services (app, frontend, db, worker)
docker compose down
docker compose exec app bash   # Shell into PHP container
```

### Backend (Symfony)
```bash
php bin/console doctrine:migrations:diff     # Generate migration after entity changes
php bin/console doctrine:migrations:migrate  # Apply pending migrations
php bin/console messenger:consume async --time-limit=3600  # Run queue worker
php bin/phpunit                              # Run all unit tests
php bin/phpunit tests/path/to/FooTest.php   # Run a single test file
php bin/phpunit --filter testMethodName      # Run a single test by name
```

### Frontend
```bash
npm run dev    # Vite dev server with HMR (runs in `frontend` Docker service)
npm run build  # Production build → output goes to Symfony's public/ directory
```

## Architecture

### Hexagonal Architecture (Ports & Adapters)
The backend is strictly layered:
- **Domain layer** — business logic, entities, value objects, use cases. No Symfony or Doctrine references here.
- **Application layer** — use cases / command handlers that orchestrate domain objects through ports (interfaces).
- **Infrastructure/adapters** — Symfony controllers, Doctrine repositories, Messenger handlers. These implement the ports and are the only layer that knows about the framework.

Domain code must never import from `Symfony\` or `Doctrine\` namespaces.

### Queue & Delivery
- Inbound webhook at `POST /in/{uuid}` persists the event and enqueues one Messenger message per active Endpoint — returns `200 OK` immediately.
- The `worker` service processes the queue and POSTs the raw body + headers to each Endpoint URL.
- Adds `X-Webhook-Event-Id: <events.id>` header to all outgoing delivery requests.
- Timeout per attempt: 10 seconds. Success = any `2xx`. 5 attempts max (immediate, 30s, 5m, 30m, 2h).

### Event Status Recomputation
`events.status` (`pending` / `delivered` / `failed`) is a denormalized cache derived from `event_endpoint_deliveries`. Rules:
- `failed` — any delivery row is `failed`
- `delivered` — all delivery rows are `delivered`
- `pending` — otherwise

**Critical constraint:** recomputation and the `event_endpoint_deliveries` update must occur atomically in the same DB transaction, querying ALL delivery rows for the event (not inferring from the single row being updated).

### Data Model Key Points
- `sources.inbound_uuid` — UUIDv7, unique, used in `POST /in/{uuid}`
- `event_endpoint_deliveries` — unique on `(event_id, endpoint_id)`; created at enqueue time, updated by worker
- `delivery_attempts` — one row per attempt (up to 5 per event+endpoint pair)
- Required indexes: `events(source_id, received_at DESC)`, `event_endpoint_deliveries(event_id)`, `delivery_attempts(event_id, endpoint_id)`

### Database Migrations
All schema changes via Doctrine Migrations only — no manual SQL. Every migration must be committed alongside the entity change that produced it.

### Frontend
- React Router for routing, `useState` + `fetch` for state (no Redux/Zustand).
- In production, the React build is served as static files from Symfony's `public/` directory — no separate Node server.

## Testing
- Unit tests only (PHPUnit). Integration tests are out of scope.
- Tests must cover domain logic and use cases in isolation.
- Use mocks/stubs for any port that crosses an infrastructure boundary (HTTP client, DB, queue).
