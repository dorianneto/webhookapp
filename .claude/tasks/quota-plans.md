# Quota Plans & Rate Limiting — Implementation Tasks

All tasks follow the architecture and constraints defined in `.claude/prd/quota-plans.md` and `CLAUDE.md`.

---

## Phase 1 — Schema & Migration

- [x] **1.1** Create `migrations/Version20260425000000.php` with all schema changes in a single migration:
  - Create `plans` table (`id` VARCHAR PK, `name` VARCHAR UNIQUE, `monthly_request_limit` INT, `created_at` TIMESTAMP)
  - Seed two rows: `plan_free` (10 000), `plan_pro` (500 000)
  - Add `plan_id` VARCHAR NULL to `users` with FK → `plans(id)` ON DELETE RESTRICT + index
  - Create `request_usage` table (`id` SERIAL PK, `user_id` FK → `users(id)` ON DELETE CASCADE, `bucket_date` DATE, `count` INT DEFAULT 0) with UNIQUE on `(user_id, bucket_date)` + index
- [ ] **1.2** Run `doctrine:migrations:migrate` and verify schema in the `db` container
- [ ] **1.3** Run `doctrine:schema:validate` — must pass with no errors

---

## Phase 2 — Domain Layer

- [x] **2.1** `src/Domain/Plan.php` — pure entity, no framework imports; fields: `id`, `name`, `monthlyRequestLimit`, `createdAt`; getters only
- [x] **2.2** `src/Domain/Exception/QuotaExceededException.php` — extends `\DomainException`
- [x] **2.3** `src/Domain/User.php` — add `private ?string $planId = null` as last optional constructor parameter + `getPlanId(): ?string`

---

## Phase 3 — Application Layer

- [x] **3.1** `src/Application/Port/PlanRepositoryPort.php` — `findByUserId(string $userId): ?Plan`
- [x] **3.2** `src/Application/Port/RequestUsageRepositoryPort.php` — `sumRolling30Days(string $userId): int` and `incrementToday(string $userId): void`
- [x] **3.3** `src/Application/Port/IngestEventDispatcherPort.php` — `dispatch(IngestCompletedEvent $event): void`
- [x] **3.4** `src/Application/Event/IngestCompletedEvent.php` — `final readonly`, fields: `userId`, `sourceId`, `eventId`, `requestId`; no framework imports

---

## Phase 4 — Doctrine Entities

- [x] **4.1** `src/Entity/Plan.php` — Doctrine entity mapping `plans` table; `toDomain(): Domain\Plan` method
- [x] **4.2** `src/Entity/RequestUsage.php` — Doctrine entity mapping `request_usage` table (for schema validation only; hot path bypasses ORM)
- [x] **4.3** `src/Entity/User.php` — add `plan_id` nullable column mapping; add `getPlanId()`/`setPlanId()`; update `fromDomain()` and `toDomain()` to include `planId`

---

## Phase 5 — Infrastructure: Persistence

- [x] **5.1** `src/Infrastructure/Persistence/DoctrinePlanRepository.php` — implements `PlanRepositoryPort`; `findByUserId` uses raw DBAL JOIN:
  ```sql
  SELECT p.* FROM plans p INNER JOIN users u ON u.plan_id = p.id WHERE u.id = :userId
  ```
  Returns `null` when `plan_id` is NULL (no join row).
- [x] **5.2** `src/Infrastructure/Persistence/DoctrineRequestUsageRepository.php` — implements `RequestUsageRepositoryPort`:
  - `sumRolling30Days`: `SELECT COALESCE(SUM(count), 0) … WHERE bucket_date >= CURRENT_DATE - 29`
  - `incrementToday`: atomic PostgreSQL upsert via DBAL — `INSERT … ON CONFLICT (user_id, bucket_date) DO UPDATE SET count = request_usage.count + 1`

---

## Phase 6 — Infrastructure: Event Dispatching

- [x] **6.1** `src/Infrastructure/EventDispatcher/SymfonyIngestEventDispatcher.php` — implements `IngestEventDispatcherPort`; wraps Symfony's `EventDispatcherInterface`
- [x] **6.2** `src/Infrastructure/EventListener/RecordRequestUsageListener.php` — `#[AsEventListener]` + `#[WithMonologChannel('hookyard')]`; listens to `IngestCompletedEvent`; calls `$usageRepository->incrementToday($event->userId)`; logs at DEBUG level

---

## Phase 7 — Use Case & Controller

- [x] **7.1** `src/Application/UseCase/Event/IngestEventUseCase.php` — add constructor deps `PlanRepositoryPort`, `RequestUsageRepositoryPort`, `IngestEventDispatcherPort`; insert quota check after source lookup and before event persistence:
  1. `findByUserId($userId)` → null → throw `QuotaExceededException` (log INFO)
  2. `sumRolling30Days($userId)` >= limit → throw `QuotaExceededException` (log INFO with `used` + `limit`)
  3. After endpoint dispatch loop: `dispatch(new IngestCompletedEvent(…))`
- [x] **7.2** `src/Controller/IngestEventController.php` — add `catch (QuotaExceededException)` → return `JsonResponse(['error' => 'Request quota exceeded.'], 429)`

---

## Phase 8 — Tests

- [x] **8.1** `IngestEventUseCaseTest` — add test cases:
  - User has no plan → `QuotaExceededException` thrown, event not persisted
  - User is at quota limit → `QuotaExceededException` thrown, event not persisted
  - User is under quota → event persisted, `IngestCompletedEvent` dispatched
- [x] **8.2** `QuotaExceededExceptionTest` — minimal: extends `\DomainException`
- [x] **8.3** Run `php bin/phpunit` — all tests pass

---

## Deployment Note

Before deploying the application code, assign a plan to all existing users to avoid immediate 429s:

```sql
UPDATE users SET plan_id = 'plan_free' WHERE plan_id IS NULL;
```

---

## Dependency Order

```
Phase 1 (migration)
  └─ Phase 2 (domain)
       └─ Phase 3 (application ports + event)
            └─ Phase 4 (doctrine entities)
                 └─ Phase 5 (persistence)
                 └─ Phase 6 (event dispatching)
                      └─ Phase 7 (use case + controller)
                           └─ Phase 8 (tests)
```
