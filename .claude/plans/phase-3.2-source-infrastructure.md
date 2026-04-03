# Phase 3.2 — Source Management: Infrastructure Layer

## Context

Phase 3.1 (port + use cases + tests) is complete. Phase 3.2 wires those use cases to Doctrine and HTTP by adding the repository implementation, a domain exception, and three API controllers.

Routes auto-register via the existing `config/routes.yaml` `api_v1` resource, which scans `src/Controller/Api/v1/` with prefix `/api/v1/`.

---

## Files to Create

### Domain Exception
- `src/Domain/Exception/SourceNotFoundException.php`

### Infrastructure
- `src/Infrastructure/Persistence/DoctrineSourceRepository.php`

### Controllers (grouped under `Source/` subfolder, matching use-case convention)
- `src/Controller/Api/v1/Source/ListSourcesController.php`
- `src/Controller/Api/v1/Source/CreateSourceController.php`
- `src/Controller/Api/v1/Source/DeleteSourceController.php`

---

## Existing Patterns to Follow

- Domain exception: `src/Domain/Exception/EmailAlreadyTakenException.php`
- Repository: `src/Infrastructure/Persistence/DoctrineUserRepository.php`
- Controller (user extraction): `src/Controller/Api/v1/MeController.php` — uses `Security::getUser()`, checks `instanceof User`
- Controller (JSON + validation): `src/Controller/Api/v1/RegistrationController.php`
- Entity mapper: `src/Entity/Source.php` — already has `fromDomain()` / `toDomain()`

---

## Implementation Plan

### Step 1 — `SourceNotFoundException` (needed by repository + controller)

```php
namespace App\Domain\Exception;
final class SourceNotFoundException extends \DomainException {}
```

### Step 2 — `DoctrineSourceRepository` (task 3.2.1)

Namespace: `App\Infrastructure\Persistence`  
Implements: `SourceRepositoryPort`

- `save`: `fromDomain()` → persist + flush  
- `findById(id, userId)`: `findOneBy(['id' => $id, 'userId' => $userId])` → `toDomain()` or null  
- `findAllByUser(userId)`: `findBy(['userId' => $userId])` → map to `toDomain()`  
- `delete(id, userId)`: `findOneBy(['id' => $id, 'userId' => $userId])` → throw `SourceNotFoundException` if null, else `remove` + flush

### Step 3 — `ListSourcesController` (task 3.2.2)

`GET /api/v1/sources`

- Extract user via `Security::getUser()`, guard with `instanceof User`
- Call `ListSourcesUseCase::execute($userId)`
- Return JSON array:
  ```json
  [{"id":"…","name":"…","inboundUuid":"…","createdAt":"…"}]
  ```

### Step 4 — `CreateSourceController` (task 3.2.3)

`POST /api/v1/sources`

- Parse body: `name` (required, not blank — validated with Symfony Validator)
- Generate source `$id = Uuid::v7()->toRfc4122()`
- Call `CreateSourceUseCase::execute($id, $userId, $name)`
- Build full inbound URL: `$request->getSchemeAndHttpHost() . '/in/' . $source->getInboundUuid()`
  - Since the ingest route (`/in/{uuid}`) doesn't exist yet (Phase 5), construct manually from request
  - Fetch the saved source via `ListSourcesUseCase` is unnecessary — re-fetch via a `findById` call on the repository, or return a synthesized response from known data
  - Cleanest: after save, reconstruct the response from `$id`, `$name`, and the `inboundUuid` returned by `CreateSourceUseCase`
  - To get `inboundUuid` back: change `CreateSourceUseCase::execute()` to return `Source` instead of `void`
- Return `201` with:
  ```json
  {"id":"…","name":"…","inboundUuid":"…","inboundUrl":"https://host/in/…","createdAt":"…"}
  ```

### Step 5 — `DeleteSourceController` (task 3.2.4)

`DELETE /api/v1/sources/{id}`

- Call `DeleteSourceUseCase::execute($id, $userId)`
- Catch `SourceNotFoundException` → `404`
- Return `204 No Content` on success

---

## Required Change to `CreateSourceUseCase`

`CreateSourceUseCase::execute()` currently returns `void`. The controller needs the generated `inboundUuid` to build the response. Change return type to `Source` (the domain object).

File: `src/Application/UseCase/Source/CreateSourceUseCase.php`  
Change: return `$source` instead of `void`; update return type to `Source`.

Update `CreateSourceUseCaseTest` accordingly.

---

## Execution Order (sequential, check off as done)

- [x] 3.2.0 — Update `CreateSourceUseCase` to return `Source`; update its test
- [x] 3.2.1 — Create `SourceNotFoundException` + `DoctrineSourceRepository`
- [x] 3.2.2 — Create `ListSourcesController`
- [x] 3.2.3 — Create `CreateSourceController`
- [x] 3.2.4 — Create `DeleteSourceController`

---

## Verification

Run unit tests to ensure no regression:
```bash
php bin/phpunit tests/Unit/Application/UseCase/Source/
```

Manual smoke test (requires running stack):
```bash
# Login first, then:
curl -s -X GET  http://localhost:8000/api/v1/sources -b cookies.txt
curl -s -X POST http://localhost:8000/api/v1/sources -b cookies.txt -H "Content-Type: application/json" -d '{"name":"Test Source"}'
curl -s -X DELETE http://localhost:8000/api/v1/sources/{id} -b cookies.txt
```
