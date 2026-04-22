# PRD: Quota Plans & Rate Limiting

## Problem

Users are abusing the ingest endpoint (`POST /in/{uuid}`) in production. There is currently no limit on how many webhook requests a user can receive, making the platform vulnerable to overuse.

## Goal

Introduce a `Plan` entity that caps the total number of inbound webhook requests a user can receive (across all their sources) in a rolling 30-day window. Exceeding the quota returns HTTP 429. Counting is event-driven and synchronous.

---

## Requirements

### Functional

- **FR-1**: A `Plan` has a name and a `monthly_request_limit` (maximum requests per rolling 30-day window).
- **FR-2**: Each user has at most one assigned plan (`users.plan_id`).
- **FR-3**: The rolling window is 30 days from the current date (not a calendar month reset).
- **FR-4**: The quota is **shared across all sources** owned by the user — it is not per-source.
- **FR-5**: When an ingest request arrives, the system checks the user's remaining quota synchronously before persisting the event.
- **FR-6**: If the user has no plan assigned, the ingest is rejected with HTTP 429.
- **FR-7**: If the user has exhausted their quota, the ingest is rejected with HTTP 429 and response body `{"error": "Request quota exceeded."}`.
- **FR-8**: When an ingest is accepted (quota check passes), the system synchronously dispatches an `IngestCompletedEvent` via a port-wrapped EventDispatcher. A listener increments the daily usage bucket within the same request. The quota limit is exact — no bursts over the limit are possible. Additional concerns (analytics, alerting, etc.) can plug in as new listeners without touching the use case.
- **FR-9**: Plans are seeded in the database via migration. Two initial tiers: `free` (10 000 req/30 days) and `pro` (500 000 req/30 days).
- **FR-10**: Plan assignment is managed via direct database update or a private admin endpoint — no self-service for MVP.

### Non-functional

- **NFR-1**: The quota check must add negligible latency to the ingest hot path (two indexed DB reads: one plan lookup, one SUM aggregation over ≤30 rows).
- **NFR-2**: The request counter is stored in daily buckets (`request_usage` table) so the rolling window query always touches at most 30 rows per user.
- **NFR-3**: The counter increment is idempotent under concurrent workers (atomic PostgreSQL upsert: `ON CONFLICT DO UPDATE`).
- **NFR-4**: All schema changes go through Doctrine Migrations. No manual SQL.
- **NFR-5**: Architecture constraints are preserved: Domain layer has zero Symfony/Doctrine imports; Application layer defines ports only; Infrastructure implements them. The `EventDispatcherInterface` dependency is hidden behind `IngestEventDispatcherPort` so the use case stays framework-free.

---

## Data Model

### `plans`
| Column | Type | Notes |
|---|---|---|
| `id` | VARCHAR(255) PK | Manually assigned string ID (e.g. `plan_free`) |
| `name` | VARCHAR(255) UNIQUE | Human-readable tier name |
| `monthly_request_limit` | INT | Max requests per rolling 30-day window |
| `created_at` | TIMESTAMP | |

Seeded rows: `plan_free` (10 000), `plan_pro` (500 000).

### `users` (altered)
| Column | Type | Notes |
|---|---|---|
| `plan_id` | VARCHAR(255) NULL | FK → `plans(id)` ON DELETE RESTRICT |

### `request_usage`
| Column | Type | Notes |
|---|---|---|
| `id` | SERIAL PK | |
| `user_id` | VARCHAR(255) NOT NULL | FK → `users(id)` ON DELETE CASCADE |
| `bucket_date` | DATE NOT NULL | One row per user per day |
| `count` | INT NOT NULL DEFAULT 0 | Incremented atomically via upsert |

Unique constraint: `(user_id, bucket_date)`. Index on `(user_id, bucket_date)`.

Rolling 30-day count = `SELECT SUM(count) FROM request_usage WHERE user_id = :id AND bucket_date >= CURRENT_DATE - 29`.

---

## Behavior

### Ingest flow (updated)

```
POST /in/{uuid}
  │
  ├─ Look up Source by inbound_uuid → 404 if not found
  │
  ├─ Look up Plan by users.plan_id (JOIN on source.user_id)
  │     └─ no plan assigned → 429
  │
  ├─ SUM request_usage for user, last 30 days
  │     └─ used >= limit → 429
  │
  ├─ Persist Event
  ├─ Enqueue DeliverEventMessage per active Endpoint
  ├─ dispatch IngestCompletedEvent (sync)
  │     └─ RecordRequestUsageListener → UPSERT (user_id, today, +1)
  │
  └─ 200 OK
```

---

## Out of Scope (MVP)

- Self-service plan upgrade/downgrade UI
- Usage dashboard for users
- Overage notifications or grace periods
- Per-source quotas
- Cleanup job for `request_usage` rows older than 30 days (can be added later)

---

## Deployment Note

After the migration runs, any existing user without a `plan_id` will receive 429 on every ingest. Before deploying the application code, assign a plan to all existing users:

```sql
UPDATE users SET plan_id = 'plan_free' WHERE plan_id IS NULL;
```
