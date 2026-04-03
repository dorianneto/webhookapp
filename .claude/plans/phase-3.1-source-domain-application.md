# Phase 3.1 — Source Management: Domain & Application Layer

## Context

Phase 2 (Authentication) is complete. Phase 3.1 adds the Domain & Application layer for Source Management — the first core business feature. Sources represent webhook receivers; each has a unique `inbound_uuid` used in the public ingest URL (`POST /in/{uuid}`).

This phase covers only the Domain and Application layers (ports, use cases, unit tests). Infrastructure (Doctrine repository, controllers) is Phase 3.2.

---

## Files to Create

### Port Interface
- `src/Application/Port/SourceRepositoryPort.php`

### Use Cases (grouped under `Source/` subfolder)
- `src/Application/UseCase/Source/CreateSourceUseCase.php`
- `src/Application/UseCase/Source/ListSourcesUseCase.php`
- `src/Application/UseCase/Source/DeleteSourceUseCase.php`

### Unit Tests
- `tests/Unit/Application/UseCase/Source/CreateSourceUseCaseTest.php`
- `tests/Unit/Application/UseCase/Source/ListSourcesUseCaseTest.php`
- `tests/Unit/Application/UseCase/Source/DeleteSourceUseCaseTest.php`

---

## Existing Patterns to Follow

- Port interface: `src/Application/Port/UserRepositoryPort.php`
- Use case: `src/Application/UseCase/RegisterUserUseCase.php`
- Domain entity (already exists): `src/Domain/Source.php`
- Test pattern: `tests/Unit/Application/UseCase/RegistrationUseCaseTest.php`
- UUIDv7 generation: `Symfony\Component\Uid\Uuid::v7()->toRfc4122()` (use case generates `inbound_uuid` internally)

---

## Implementation Plan

### Step 1 — `SourceRepositoryPort` (task 3.1.1)

Namespace: `App\Application\Port`

```php
interface SourceRepositoryPort {
    public function save(Source $source): void;
    public function findById(string $id, string $userId): ?Source;
    public function findAllByUser(string $userId): array;   // returns Source[]
    public function delete(string $id, string $userId): void;
}
```

### Step 2 — `CreateSourceUseCase` (task 3.1.2)

Namespace: `App\Application\UseCase\Source`

- Accepts: `string $id`, `string $userId`, `string $name`
- Internally generates `inboundUuid` via `Uuid::v7()->toRfc4122()`
- Constructs `Domain\Source` and calls `$port->save()`

### Step 3 — `ListSourcesUseCase` (task 3.1.3)

Namespace: `App\Application\UseCase\Source`

- Accepts: `string $userId`
- Returns: `Source[]` from `$port->findAllByUser($userId)`

### Step 4 — `DeleteSourceUseCase` (task 3.1.4)

Namespace: `App\Application\UseCase\Source`

- Accepts: `string $id`, `string $userId`
- Calls `$port->delete($id, $userId)` — hard delete (no cascade per spec)

### Step 5 — Unit Tests

Namespace: `App\Tests\Unit\Application\UseCase\Source`

Each test file follows `RegistrationUseCaseTest.php` pattern:
- `setUp()` mocks `SourceRepositoryPort`, instantiates use case
- `CreateSourceUseCaseTest`: verify `save()` called with correct Source (id, userId, name; inboundUuid is a non-empty string)
- `ListSourcesUseCaseTest`: verify `findAllByUser()` called with userId; returns port result
- `DeleteSourceUseCaseTest`: verify `delete()` called with correct id + userId

---

## Execution Order (sequential, check off as done)

- [x] 3.1.1 — Create `SourceRepositoryPort`
- [x] 3.1.2 — Create `CreateSourceUseCase`
- [x] 3.1.3 — Create `ListSourcesUseCase`
- [x] 3.1.4 — Create `DeleteSourceUseCase`
- [x] Unit tests — `CreateSourceUseCaseTest`, `ListSourcesUseCaseTest`, `DeleteSourceUseCaseTest`

---

## Verification

```bash
php bin/phpunit tests/Unit/Application/UseCase/Source/CreateSourceUseCaseTest.php
php bin/phpunit tests/Unit/Application/UseCase/Source/ListSourcesUseCaseTest.php
php bin/phpunit tests/Unit/Application/UseCase/Source/DeleteSourceUseCaseTest.php
```

All tests must pass with no warnings.
