# Product Requirements Document — Webhook-as-a-Service (MVP)

**Stack:** Symfony 7 (API) + React (Frontend) — Monolith
**Principle:** Ship the smallest thing that works end-to-end. No premature abstractions.

---

## 1. Problem statement

Developers who receive webhooks from third-party services (Stripe, GitHub, Shopify, etc.) have to build reliability infrastructure themselves: signature verification, retries, queuing, delivery logs. This PRD specifies an MVP platform that owns that infrastructure so developers don't have to.

---

## 2. Goals

- A user can register one or more endpoint URLs under a Source and receive a unique inbound webhook URL from the platform.
- Events posted to that inbound URL are validated, queued, and delivered to all of the Source's endpoints with automatic retries.
- A user can see delivery attempts and their outcomes in a simple dashboard.

## 3. Non-goals (explicitly out of scope for MVP)

- Custom retry policies per endpoint
- Team accounts or multi-user workspaces
- Webhook transformations or filtering
- SDK or CLI tooling
- Billing or usage limits
- Advanced alerting or webhooks-about-webhooks

---

## 4. User roles

| Role | Description |
|---|---|
| **User** | A registered developer who creates and manages endpoints |
| **Sender** | Any external service (Stripe, GitHub, etc.) that POSTs to an inbound URL — not a registered user |

---

## 5. Core concepts

| Concept | Definition |
|---|---|
| **Source** | A named inbound webhook URL created by a user (e.g. "My Stripe account") |
| **Endpoint** | One destination URL belonging to a Source; a Source can have many Endpoints |
| **Event** | A single webhook payload received at a Source's inbound URL |
| **Delivery attempt** | One HTTP POST to a specific Endpoint for a given Event |

---

## 6. Features

### 6.1 Authentication

- Email + password registration and login.
- Session-based auth (Symfony's built-in security component).
- No OAuth, no magic links — keep it simple.

### 6.2 Source management

A user can:
- Create a Source with a name.
- View their list of Sources.
- Delete a Source (stops delivery; does not delete historical events or endpoints).

Each Source gets a system-generated **inbound URL** in the form:

```
https://yourdomain.com/in/{uuid}
```

No shared secret is required from the sender in MVP — the inbound URL itself is the shared secret (unguessable UUID). Signature verification can be added in v2.

### 6.3 Endpoint management

A Source can have one or more Endpoints. A user can:
- Add an Endpoint URL to a Source.
- View all Endpoints for a Source.
- Delete an Endpoint from a Source (stops future delivery to that URL; does not affect historical delivery attempts).

When an Event arrives, the platform fans out and enqueues a separate delivery job for **each active Endpoint** on the Source. Endpoints are independent — a failure on one does not affect delivery to the others.

### 6.4 Event ingestion (inbound endpoint)

`POST /in/{uuid}`

- Accept any `Content-Type` (store raw body as-is).
- Respond `200 OK` immediately — before any processing.
- Persist the raw payload, headers, and received timestamp to the database.
- Enqueue one delivery job **per active Endpoint** on the Source.
- Return early. Never block on delivery.

If the Source UUID is unknown → `404`. That is the only rejection case in MVP.

### 6.5 Event delivery

- A Symfony Messenger worker processes the queue.
- Each job targets a specific Endpoint — the worker POSTs the original raw body + headers to that Endpoint's URL.
- Adds a `X-Webhook-Event-Id` header so the receiver can deduplicate. The value MUST be the internal `events.id` of the persisted event record.

> **Idempotency:** The platform does NOT guarantee idempotent delivery in MVP. Duplicate events MAY occur (e.g. after a retry following a transient failure where the endpoint processed the request but failed to respond in time). Endpoint consumers are responsible for handling duplicates using the `X-Webhook-Event-Id` header.

- Timeout per attempt: **10 seconds**.
- Success: any `2xx` response.
- Failure: anything else (non-2xx, timeout, connection error).
- A failure on one Endpoint's delivery job does not affect the jobs for other Endpoints of the same Source.

**Retry schedule (exponential backoff):**

| Attempt | Delay |
|---|---|
| 1 | Immediate |
| 2 | 30 seconds |
| 3 | 5 minutes |
| 4 | 30 minutes |
| 5 | 2 hours |

After attempt 5 fails, the `event_endpoint_deliveries` row for that endpoint is marked **failed**. No further retries. No DLQ UI in MVP — just a status flag in the database.

### 6.6 Dashboard

A simple, read-only dashboard for each Source:

- List of recent Events (last 100), showing: received timestamp, HTTP method, overall status (`pending` / `delivered` / `failed`).
  - An Event is `delivered` when all its Endpoints have been successfully delivered to.
  - An Event is `failed` when at least one Endpoint has exhausted all retries.
  - An Event is `pending` while any delivery is still in progress.
- Clicking an Event shows:
  - Raw request headers and body received.
  - Per-endpoint delivery status: for each Endpoint, the list of delivery attempts with timestamp, HTTP status returned, response body (truncated to 500 chars, valid UTF-8), and duration.
- No filtering, no search in MVP.

---

## 7. Data model

### `users`
| Column | Type | Notes |
|---|---|---|
| id | int PK | |
| email | varchar(255) | unique |
| password | varchar(255) | hashed |
| created_at | timestamp | |

### `sources`
| Column | Type | Notes |
|---|---|---|
| id | int PK | |
| user_id | int FK → users | |
| name | varchar(100) | |
| inbound_uuid | uuid | unique; generated with `uuidv7()` on create |
| created_at | timestamp | |

### `endpoints`
| Column | Type | Notes |
|---|---|---|
| id | int PK | |
| source_id | int FK → sources | |
| url | varchar(2048) | destination URL |
| created_at | timestamp | |

### `events`
| Column | Type | Notes |
|---|---|---|
| id | int PK | |
| source_id | int FK → sources | |
| method | varchar(10) | e.g. POST |
| headers | json | raw inbound headers |
| body | text | raw inbound body |
| status | enum | `pending`, `delivered`, `failed` — denormalised cache, derived from `event_endpoint_deliveries` |
| received_at | timestamp | |

### `event_endpoint_deliveries`
| Column | Type | Notes |
|---|---|---|
| id | int PK | |
| event_id | int FK → events | |
| endpoint_id | int FK → endpoints | |
| status | enum | `pending`, `delivered`, `failed` |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique constraint on `(event_id, endpoint_id)`. One row per event+endpoint pair, created when the delivery job is enqueued and updated by the worker after each attempt cycle.

### `delivery_attempts`
| Column | Type | Notes |
|---|---|---|
| id | int PK | |
| event_id | int FK → events | |
| endpoint_id | int FK → endpoints | |
| attempt_number | smallint | 1–5 |
| status_code | smallint | HTTP status returned, null on timeout |
| response_body | text | truncated to 500 chars; MUST remain valid UTF-8 after truncation |
| duration_ms | int | |
| attempted_at | timestamp | |

---

## 8. API routes (Symfony)

| Method | Path | Description |
|---|---|---|
| POST | `/register` | Create account |
| POST | `/login` | Log in |
| POST | `/logout` | Log out |
| GET | `/api/sources` | List user's sources |
| POST | `/api/sources` | Create a source |
| DELETE | `/api/sources/{id}` | Delete a source |
| GET | `/api/sources/{id}/endpoints` | List endpoints for a source |
| POST | `/api/sources/{id}/endpoints` | Add an endpoint to a source |
| DELETE | `/api/endpoints/{id}` | Delete an endpoint |
| GET | `/api/sources/{id}/events` | List events for a source (last 100, ordered by `received_at` desc) |
| GET | `/api/events/{id}` | Event detail + per-endpoint delivery attempts |
| POST | `/in/{uuid}` | Inbound webhook receiver (public) |

All `/api/*` routes require authentication. `/in/{uuid}` is intentionally public.

---

## 9. Frontend routes (React)

| Path | View |
|---|---|
| `/login` | Login form |
| `/register` | Registration form |
| `/` | List of user's Sources |
| `/sources/new` | Create source form |
| `/sources/{id}` | Source detail — endpoint list + event list |
| `/sources/{id}/endpoints/new` | Add endpoint form |
| `/sources/{id}/events/{eventId}` | Event detail — per-endpoint delivery attempts |

Use React Router. No state management library in MVP — `useState` + `fetch` is sufficient.

---

## 10. Queue setup

Use **Symfony Messenger** with the **Doctrine transport** (stores jobs in a `messenger_messages` database table). This avoids a Redis/RabbitMQ dependency and keeps the MVP a true monolith.

Run the worker as a background process:

```bash
php bin/console messenger:consume async --time-limit=3600
```

In production, manage the worker process with systemd or a process manager. In development, it runs as a dedicated Docker Compose service (see section 11).

---

## 11. Implementation notes

### Database migrations

All database schema changes MUST be managed through Doctrine Migrations (`doctrine/doctrine-migrations-bundle`). Migrations must be generated from and kept in sync with the Doctrine entity definitions that reflect the data model in section 7. No manual schema changes are permitted directly against the database.

The expected workflow is:

```bash
# After modifying an entity, generate a migration
php bin/console doctrine:migrations:diff

# Review the generated SQL, then apply
php bin/console doctrine:migrations:migrate
```
Every migration file must be committed to version control alongside the entity change that produced it.


### Required indexes

The following indexes MUST be created via Doctrine migrations alongside the relevant entity definitions:

| Table | Index columns | Notes |
|---|---|---|
| `events` | `(source_id, received_at)` | Composite — filters by source and orders by date in one scan; `received_at` should be sorted desc |
| `event_endpoint_deliveries` | `event_id` | Foreign key lookup — fetch all delivery rows for an event |
| `delivery_attempts` | `(event_id, endpoint_id)` | Composite — fetch attempts per event+endpoint pair |

### Event status recomputation

`events.status` MUST be recomputed after every update to an `event_endpoint_deliveries` row. The following rules are mandatory:

- The recomputation MUST query **all** `event_endpoint_deliveries` rows for the event and derive status from the full set — never inferred from the single row being updated.
- The recomputation and the `event_endpoint_deliveries` update MUST occur **atomically within the same database transaction**. No other process must be able to observe an intermediate state where the delivery row has been updated but `events.status` has not.
- "Last write wins" is NOT acceptable — concurrent workers updating different endpoints for the same event must both produce a consistent final status.
- The derivation rule is deterministic and fixed:
  - `failed` — if **any** `event_endpoint_deliveries` row has status `failed`
  - `delivered` — if **all** `event_endpoint_deliveries` rows have status `delivered`
  - `pending` — otherwise

### Design principles

The backend MUST follow **SOLID** as its primary design principle throughout all layers of the application.

### Architecture

The backend MUST be structured following **Hexagonal Architecture** (Ports and Adapters). Business logic lives in the domain layer and is completely isolated from infrastructure concerns (HTTP, database, queue). Symfony-specific code (controllers, Doctrine repositories, Messenger handlers) are adapters — they are never referenced from within the domain.

### Testing

All backend code MUST have **unit tests** written with **PHPUnit**. Integration tests are explicitly out of scope for now. Tests must cover domain logic and application use cases in isolation, using mocks or stubs for any port that crosses an infrastructure boundary.

### Local development environment

The application MUST be fully Dockerized for the development environment using **Docker Compose**. Every developer must be able to start the full stack with a single command and without installing PHP, Node, or PostgreSQL locally. The Compose setup must include at minimum the following services:

- `app` — PHP 8.3 + Symfony running via the Symfony CLI local server (`symfony server:start`). Requires the [Symfony CLI](https://symfony.com/download) binary to be installed in the container.
- `frontend` — Node + Vite dev server with hot module replacement
- `db` — PostgreSQL 17
- `worker` — Symfony Messenger consumer (`messenger:consume async`)

---

## 12. Technical constraints

- **PHP 8.3+**, **Symfony 7.x**
- **React 18+**, bundled with **Vite**
- **PostgreSQL 17**
- In production, the React app is served as a static build from Symfony's `public/` directory — no separate Node server
- No external queue broker in MVP (Doctrine transport only)
- HTTPS required in production (inbound webhook URLs must be reachable)

---

## 13. Out-of-scope technical decisions (decide later)

- HMAC signature verification on inbound requests
- Horizontal scaling of the worker (needs a real broker at that point)
- Event storage limits / TTL / pruning
- Rate limiting on `/in/{uuid}`
- Observability / structured logging beyond Symfony's defaults

---

## 14. Success criteria for MVP

1. A user can sign up, create a Source, add an Endpoint, and get an inbound URL in under 2 minutes.
2. A webhook POSTed to the inbound URL is delivered to all active Endpoints within 5 seconds under normal conditions.
3. If one Endpoint is down, the platform retries it independently without affecting delivery to other Endpoints.
4. A user can inspect any event's raw payload and every delivery attempt per Endpoint in the dashboard.
