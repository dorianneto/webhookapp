# Product Requirements Document — Foreign Key Constraints

**Scope:** Database layer only. No application logic, no entity relationship annotations.

---

## 1. Problem statement

The database currently has no foreign key constraints. All relationships between tables (`source_id`, `endpoint_id`, `event_id`, `user_id`) are bare string columns with no referential integrity enforcement. This allows orphaned rows to accumulate silently and makes cascading deletions unreliable — they must be handled entirely in application code, which is error-prone.

---

## 2. Goals

- Enforce referential integrity at the database level for all inter-table relationships.
- Define the correct delete behaviour for each relationship so the database handles cascades automatically.
- Ship as a single, reversible Doctrine migration — no changes to entities, use cases, or application logic.

---

## 3. Deletion rules

### Source deletion
- A **Source cannot be deleted** while it has any associated **Endpoints** → `RESTRICT`
- Deleting a Source **must delete** all its **Events** → `CASCADE`
  - Deleting an Event (via cascade) **must delete** all its `event_endpoint_deliveries` rows → `CASCADE`
  - Deleting an Event (via cascade) **must delete** all its `delivery_attempts` rows → `CASCADE`

### Endpoint deletion
- Deleting an Endpoint **must delete** all its `event_endpoint_deliveries` rows → `CASCADE`
- Deleting an Endpoint **must delete** all its `delivery_attempts` rows → `CASCADE`

---

## 4. Foreign key specification

| Constraint name | Child table | Child column | Parent table | Parent column | On Delete |
|---|---|---|---|---|---|
| `fk_sources_user_id` | `sources` | `user_id` | `users` | `id` | `RESTRICT` |
| `fk_endpoints_source_id` | `endpoints` | `source_id` | `sources` | `id` | `RESTRICT` |
| `fk_events_source_id` | `events` | `source_id` | `sources` | `id` | `CASCADE` |
| `fk_eed_event_id` | `event_endpoint_deliveries` | `event_id` | `events` | `id` | `CASCADE` |
| `fk_eed_endpoint_id` | `event_endpoint_deliveries` | `endpoint_id` | `endpoints` | `id` | `CASCADE` |
| `fk_da_event_id` | `delivery_attempts` | `event_id` | `events` | `id` | `CASCADE` |
| `fk_da_endpoint_id` | `delivery_attempts` | `endpoint_id` | `endpoints` | `id` | `CASCADE` |

---

## 5. Implementation

### Migration
Create one new Doctrine migration file (`migrations/VersionYYYYMMDDHHmmss.php`).

**`up()`** — add all seven FK constraints using `ALTER TABLE … ADD CONSTRAINT … FOREIGN KEY (…) REFERENCES … ON DELETE …`.

**`down()`** — drop all seven FK constraints using `ALTER TABLE … DROP CONSTRAINT …`.

Order matters in `up()`: add constraints on parent tables before child tables (users → sources → endpoints/events → event_endpoint_deliveries/delivery_attempts).

Reverse the order in `down()`.

### No other changes
- No changes to Doctrine entity files.
- No changes to application or domain code.
- No changes to use cases, repositories, or controllers.

---

## 6. Out of scope

- `ON DELETE SET NULL` or `ON UPDATE CASCADE` behaviours — not needed for this data model.
- Doctrine `cascade: [...]` ORM-level options — this PRD targets database constraints only.
- Any UI or API changes related to enforcing these rules in application responses.
