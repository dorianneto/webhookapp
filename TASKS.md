# WaaS MVP — Implementation Tasks

All tasks follow the architecture and constraints defined in `PRD.md` and `CLAUDE.md`.
**Stack:** Symfony 7 / PHP 8.3 · React 18 + Vite · PostgreSQL 18 · Symfony Messenger (Doctrine transport)
**Architecture:** Hexagonal (Ports & Adapters) · SOLID · PHPUnit unit tests

---

## Phase 0 — Project Scaffolding

- [x] **0.1** Initialise Symfony 7 project (`symfony new . --version="7.*" --webapp`)
- [x] **0.2** Initialise React 18 + Vite frontend inside `frontend/` directory
- [x] **0.3** Configure Vite `build.outDir` to output to `public/build/` so Symfony can serve the static build
- [ ] **0.4** Write `docker-compose.yml` with four services:
  - `app` — PHP 8.3 + Symfony CLI (`symfony server:start`)
  - `frontend` — Node 20 + Vite dev server (HMR on port 5173)
  - `db` — PostgreSQL 18
  - `worker` — reuses the `app` image, runs `messenger:consume async --time-limit=3600`
- [ ] **0.5** Write `Dockerfile` for the `app`/`worker` image (PHP 8.3-cli, Composer, Symfony CLI binary)
- [ ] **0.6** Write `Dockerfile` for the `frontend` image (Node 20, installs deps, starts Vite)
- [ ] **0.7** Add `.env` / `.env.local` template with `DATABASE_URL`, `APP_ENV`, `APP_SECRET`
- [ ] **0.8** Verify `docker compose up` starts all four services with no errors

---

## Phase 1 — Database & Doctrine Entities

### 1.1 Install dependencies
- [ ] Add `doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`, `symfony/uid` via Composer

### 1.2 Domain entities (no Symfony/Doctrine imports in domain layer)
- [ ] **1.2.1** `Domain/User` — id, email, password hash, createdAt
- [ ] **1.2.2** `Domain/Source` — id, userId, name, inboundUuid (UUIDv7), createdAt
- [ ] **1.2.3** `Domain/Endpoint` — id, sourceId, url, createdAt
- [ ] **1.2.4** `Domain/Event` — id, sourceId, method, headers (array), body (string), status (enum: pending/delivered/failed), receivedAt
- [ ] **1.2.5** `Domain/EventEndpointDelivery` — id, eventId, endpointId, status (enum: pending/delivered/failed), createdAt, updatedAt; unique on (eventId, endpointId)
- [ ] **1.2.6** `Domain/DeliveryAttempt` — id, eventId, endpointId, attemptNumber (1–5), statusCode (nullable), responseBody (≤500 chars, valid UTF-8), durationMs, attemptedAt

### 1.3 Doctrine mappings (Infrastructure layer — XML or attribute mappings on separate ORM entities or mapped superclasses)
- [ ] Map each domain entity to its table with correct column types
- [ ] `sources.inbound_uuid` → `uuid` column type, unique constraint, generated with UUIDv7
- [ ] `events.status`, `event_endpoint_deliveries.status` → PostgreSQL `enum` or Doctrine `string` with value validation
- [ ] Unique constraint on `event_endpoint_deliveries(event_id, endpoint_id)`

### 1.4 Required indexes (created via migrations)
- [ ] `events(source_id, received_at DESC)` — composite
- [ ] `event_endpoint_deliveries(event_id)`
- [ ] `delivery_attempts(event_id, endpoint_id)` — composite

### 1.5 Migrations
- [ ] Run `doctrine:migrations:diff` to generate initial migration
- [ ] Review generated SQL for correctness (types, constraints, indexes)
- [ ] Run `doctrine:migrations:migrate` and verify schema in the `db` container
- [ ] Commit migration file alongside entity definitions

---

## Phase 2 — Authentication

### 2.1 Backend
- [ ] **2.1.1** Install `symfony/security-bundle`, `symfony/password-hasher`
- [ ] **2.1.2** Configure `security.yaml`: user provider backed by Doctrine, bcrypt/argon2 password hasher, stateless JSON firewall for `/api/*`, form-login or JSON-login for `/login`
- [ ] **2.1.3** `POST /register` controller — validate email uniqueness, hash password, persist user, return `201`
- [ ] **2.1.4** `POST /login` controller — Symfony handles credential check; return `200` with session cookie or JSON user info
- [ ] **2.1.5** `POST /logout` controller — invalidate session, return `200`
- [ ] Unit tests: `RegistrationUseCaseTest`, `UserRepositoryPortTest` (mock)

### 2.2 Frontend
- [ ] **2.2.1** `/register` route — form with email + password + confirm password; POST to `/register`; redirect to `/` on success
- [ ] **2.2.2** `/login` route — form with email + password; POST to `/login`; redirect to `/` on success
- [ ] **2.2.3** Global auth state — store logged-in user in React context or top-level `useState`; redirect unauthenticated users to `/login`

---

## Phase 3 — Source Management

### 3.1 Domain & Application layer
- [ ] **3.1.1** Port interface `SourceRepositoryPort` — `save(Source)`, `findById(id, userId)`, `findAllByUser(userId)`, `delete(id, userId)`
- [ ] **3.1.2** Use case `CreateSourceUseCase` — generates UUIDv7 for `inbound_uuid`, persists via port
- [ ] **3.1.3** Use case `ListSourcesUseCase`
- [ ] **3.1.4** Use case `DeleteSourceUseCase` — marks source as deleted (or hard-deletes); does not cascade to events or endpoints
- [ ] Unit tests for all three use cases with mocked `SourceRepositoryPort`

### 3.2 Infrastructure
- [ ] **3.2.1** `DoctrineSourceRepository` implements `SourceRepositoryPort`
- [ ] **3.2.2** `GET /api/sources` controller → `ListSourcesUseCase` → JSON array
- [ ] **3.2.3** `POST /api/sources` controller → `CreateSourceUseCase` → `201` with source JSON (including full inbound URL)
- [ ] **3.2.4** `DELETE /api/sources/{id}` controller → `DeleteSourceUseCase` → `204`

### 3.3 Frontend
- [ ] **3.3.1** `/` route — fetch and display sources list; show inbound URL, name, created date; link to `/sources/{id}`; button → `/sources/new`
- [ ] **3.3.2** `/sources/new` route — form with name field; POST to `/api/sources`; redirect to `/sources/{id}` on success
- [ ] Delete source button on list with confirmation prompt

---

## Phase 4 — Endpoint Management

### 4.1 Domain & Application layer
- [ ] **4.1.1** Port interface `EndpointRepositoryPort` — `save(Endpoint)`, `findById(id)`, `findAllBySource(sourceId)`, `delete(id)`, `findActiveBySource(sourceId)`
- [ ] **4.1.2** Use case `AddEndpointUseCase` — validates URL format, persists endpoint
- [ ] **4.1.3** Use case `ListEndpointsUseCase`
- [ ] **4.1.4** Use case `DeleteEndpointUseCase`
- [ ] Unit tests for all three use cases

### 4.2 Infrastructure
- [ ] **4.2.1** `DoctrineEndpointRepository` implements `EndpointRepositoryPort`
- [ ] **4.2.2** `GET /api/sources/{id}/endpoints` controller → `ListEndpointsUseCase`
- [ ] **4.2.3** `POST /api/sources/{id}/endpoints` controller → `AddEndpointUseCase` → `201`
- [ ] **4.2.4** `DELETE /api/endpoints/{id}` controller → `DeleteEndpointUseCase` → `204`

### 4.3 Frontend
- [ ] **4.3.1** `/sources/{id}` route — show endpoint list alongside event list; each endpoint shows URL and created date; delete button per endpoint
- [ ] **4.3.2** `/sources/{id}/endpoints/new` route — form with URL field; POST to `/api/sources/{id}/endpoints`; redirect back to `/sources/{id}` on success

---

## Phase 5 — Event Ingestion

### 5.1 Domain & Application layer
- [ ] **5.1.1** Port interface `EventRepositoryPort` — `save(Event)`, `findById(id)`, `findRecentBySource(sourceId, limit)`, `updateStatus(id, status)`
- [ ] **5.1.2** Port interface `DeliveryQueuePort` — `enqueue(DeliverEventMessage)`
- [ ] **5.1.3** Use case `IngestEventUseCase`:
  1. Lookup source by `inbound_uuid` — throw `SourceNotFoundException` if not found
  2. Persist the `Event` (status = `pending`)
  3. Fetch all active endpoints for the source
  4. For each endpoint, create an `EventEndpointDelivery` row (status = `pending`)
  5. Enqueue one `DeliverEventMessage` per endpoint via `DeliveryQueuePort`
  6. Return immediately
- [ ] Unit tests: source not found, no endpoints (still 200), multiple endpoints enqueued

### 5.2 Infrastructure
- [ ] **5.2.1** `DoctrineEventRepository` implements `EventRepositoryPort`
- [ ] **5.2.2** `MessengerDeliveryQueue` implements `DeliveryQueuePort` (wraps `MessageBusInterface`)
- [ ] **5.2.3** `DeliverEventMessage` DTO — carries `eventId`, `endpointId`, `attemptNumber`
- [ ] **5.2.4** `POST /in/{uuid}` controller (public, no auth):
  - Capture raw body (`Request::getContent()`), all headers, HTTP method
  - Call `IngestEventUseCase`
  - On `SourceNotFoundException` → `404`
  - Always return `200 OK` immediately

---

## Phase 6 — Event Delivery Worker

### 6.1 Domain & Application layer
- [ ] **6.1.1** Port interface `HttpDeliveryPort` — `deliver(url, headers, body, timeoutSeconds): DeliveryResult`
- [ ] **6.1.2** `DeliveryResult` value object — statusCode (nullable), responseBody (truncated, valid UTF-8), durationMs, success (bool)
- [ ] **6.1.3** Port interface `DeliveryAttemptRepositoryPort` — `save(DeliveryAttempt)`, `countByEventAndEndpoint(eventId, endpointId)`
- [ ] **6.1.4** Port interface `EventEndpointDeliveryRepositoryPort` — `findOrCreate(eventId, endpointId)`, `updateStatus(id, status)`, `findAllByEvent(eventId)`
- [ ] **6.1.5** Use case `ProcessDeliveryUseCase`:
  1. Load `EventEndpointDelivery` and `Event` (get raw body + headers)
  2. POST via `HttpDeliveryPort` with 10-second timeout; add `X-Webhook-Event-Id: <events.id>` header
  3. Record `DeliveryAttempt` row
  4. **Within a single DB transaction:**
     - Update `EventEndpointDelivery.status` (delivered on 2xx, else pending/failed)
     - Query ALL `EventEndpointDelivery` rows for the event
     - Recompute and update `events.status` using the deterministic rule
  5. If failed and `attemptNumber < 5`: re-enqueue with delay (30s → 5m → 30m → 2h)
  6. If failed and `attemptNumber == 5`: mark delivery as `failed`, recompute event status
- [ ] Unit tests: success on first attempt, retry scheduling, exhausted retries, event status recomputation (concurrent delivery scenarios covered via mocks)

### 6.2 Infrastructure
- [ ] **6.2.1** `GuzzleHttpDeliveryAdapter` (or `SymfonyHttpClientAdapter`) implements `HttpDeliveryPort`
  - Truncate `responseBody` to 500 chars preserving valid UTF-8 (`mb_substr`)
  - Catch connection/timeout exceptions → `DeliveryResult` with `success = false`, null `statusCode`
- [ ] **6.2.2** `DoctrineDeliveryAttemptRepository` implements `DeliveryAttemptRepositoryPort`
- [ ] **6.2.3** `DoctrineEventEndpointDeliveryRepository` implements `EventEndpointDeliveryRepositoryPort`
- [ ] **6.2.4** `DeliverEventMessageHandler` — Symfony Messenger handler for `DeliverEventMessage`; calls `ProcessDeliveryUseCase`; uses Messenger's built-in retry stamps for delay scheduling
- [ ] **6.2.5** Configure `messenger.yaml`: `async` transport → Doctrine, retry strategy disabled (manual retry management in use case), worker time-limit

---

## Phase 7 — Dashboard (Events & Delivery Attempts)

### 7.1 Domain & Application layer
- [ ] **7.1.1** Use case `ListEventsUseCase` — last 100 events for a source ordered by `received_at DESC`; returns id, receivedAt, method, status
- [ ] **7.1.2** Use case `GetEventDetailUseCase` — returns raw headers + body + per-endpoint delivery status + all `DeliveryAttempt` rows for the event

### 7.2 Infrastructure
- [ ] **7.2.1** `GET /api/sources/{id}/events` controller → `ListEventsUseCase` → JSON array (100 items max)
- [ ] **7.2.2** `GET /api/events/{id}` controller → `GetEventDetailUseCase` → JSON with nested structure:
  ```json
  {
    "id": 1,
    "method": "POST",
    "headers": {...},
    "body": "...",
    "status": "delivered",
    "receivedAt": "...",
    "deliveries": [
      {
        "endpointId": 1,
        "endpointUrl": "https://...",
        "status": "delivered",
        "attempts": [
          { "attemptNumber": 1, "statusCode": 200, "responseBody": "...", "durationMs": 120, "attemptedAt": "..." }
        ]
      }
    ]
  }
  ```

### 7.3 Frontend
- [ ] **7.3.1** `/sources/{id}` route — event list section below endpoint list; show received timestamp, method, status badge (`pending`/`delivered`/`failed`); click → `/sources/{id}/events/{eventId}`; poll or manual refresh
- [ ] **7.3.2** `/sources/{id}/events/{eventId}` route — event detail page:
  - Raw headers and body panel
  - Per-endpoint accordion/table: endpoint URL, overall status, attempt rows (timestamp, HTTP status, response body, duration)

---

## Phase 8 — Cross-cutting Concerns

- [ ] **8.1** Authentication guard — all `/api/*` routes return `401` for unauthenticated requests; frontend redirects to `/login` on `401`
- [ ] **8.2** Authorization — users can only access their own sources/events/endpoints (check `source.user_id` in every use case / query)
- [ ] **8.3** Input validation — reject empty source names, invalid endpoint URLs (use Symfony Validator or domain-layer assertions); return `422` with error details
- [ ] **8.4** CORS — configure Symfony to allow requests from the Vite dev server origin in development
- [ ] **8.5** Error responses — consistent JSON error shape `{ "error": "message" }` for `4xx`/`5xx`
- [ ] **8.6** UUIDv7 generation utility — confirmed available via `symfony/uid` (`Uuid::v7()`)

---

## Phase 9 — Testing

- [ ] **9.1** `CreateSourceUseCaseTest`
- [ ] **9.2** `DeleteSourceUseCaseTest`
- [ ] **9.3** `AddEndpointUseCaseTest`
- [ ] **9.4** `IngestEventUseCaseTest` — source not found, no active endpoints, multiple endpoints enqueued
- [ ] **9.5** `ProcessDeliveryUseCaseTest`:
  - 2xx response → delivery marked delivered, event status recomputed
  - Non-2xx on attempt 1–4 → re-enqueued with correct delay
  - Failure on attempt 5 → delivery marked failed, event status recomputed
  - Mixed endpoint results → event status derivation (any-failed, all-delivered, otherwise-pending)
- [ ] **9.6** `EventStatusRecomputationTest` — all delivery rows queried (not just updated row); atomicity ensured by transaction mock
- [ ] **9.7** `RegistrationUseCaseTest` — duplicate email returns error
- [ ] **9.8** Verify all tests pass: `php bin/phpunit`

---

## Phase 10 — Production Readiness

- [ ] **10.1** `npm run build` outputs static assets to `public/build/`; Symfony serves them via `public/index.php` fallback
- [ ] **10.2** Symfony `webpack_encore` or Vite manifest integration — ensure asset paths resolve correctly in production
- [ ] **10.3** `APP_ENV=prod` configuration — disable debug, enable OPcache
- [ ] **10.4** Document `docker compose up` as the single start command in README (optional — only if explicitly requested)
- [ ] **10.5** Confirm all four Docker services start cleanly and end-to-end flow works:
  1. Register → login → create source → copy inbound URL
  2. Add endpoint
  3. `curl -X POST <inbound_url> -d '{"test":1}'`
  4. Verify event appears in dashboard with delivery attempt details

---

## Dependency Order (critical path)

```
Phase 0 (scaffolding)
  └─ Phase 1 (DB entities + migrations)
       ├─ Phase 2 (auth)
       ├─ Phase 3 (sources)          ─┐
       └─ Phase 4 (endpoints)         ├─ Phase 5 (ingestion) ─── Phase 6 (delivery worker)
                                      └─────────────────────────────── Phase 7 (dashboard)
Phase 8 (cross-cutting) — woven into every phase above
Phase 9 (tests) — written alongside each phase
Phase 10 (prod) — last
```
