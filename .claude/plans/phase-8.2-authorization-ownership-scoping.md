# Phase 8.2 — Authorization (ownership scoping)

## Context
All endpoint and event use cases accept no `userId`, so any authenticated user can access any source's data. `SourceRepositoryPort::findById(id, userId)` already returns null when the source doesn't belong to the user — use it as the ownership check throughout the ownership chain (Source → Endpoint/Event via sourceId).

Returning 404 (not 403) on ownership failure avoids information disclosure.

## Ownership chain
- Source has `userId` — already scoped in `SourceRepositoryPort`
- Endpoint has `sourceId` — verify via `sourceRepository->findById(sourceId, userId)`
- Event has `sourceId` — same pattern
- For `DeleteEndpoint`/`GetEventDetail` where no `sourceId` is in the URL: load the entity first to get `sourceId`, then verify

## Use Case changes (Application Layer)

### `AddEndpointUseCase` — `src/Application/UseCase/Endpoint/AddEndpointUseCase.php`
- Add `SourceRepositoryPort` constructor dependency
- Add `string $userId` to `execute(id, sourceId, url)` → `execute(id, sourceId, url, userId)`
- Before saving: `sourceRepository->findById(sourceId, userId)` → null → throw `SourceNotFoundException`

### `ListEndpointsUseCase` — `src/Application/UseCase/Endpoint/ListEndpointsUseCase.php`
- Add `SourceRepositoryPort` constructor dependency
- Add `string $userId` to `execute(sourceId)` → `execute(sourceId, userId)`
- Before querying: verify ownership, throw `SourceNotFoundException` if null

### `DeleteEndpointUseCase` — `src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php`
- Add `SourceRepositoryPort` constructor dependency (already has `EndpointRepositoryPort`)
- Add `string $userId` to `execute(id)` → `execute(id, userId)`
- Load endpoint via `endpointRepository->findById(id)` → null → throw `EndpointNotFoundException`
- Verify source ownership → null → throw `SourceNotFoundException`
- Then call `endpointRepository->delete(id)`

### `ListEventsUseCase` — `src/Application/UseCase/Event/ListEventsUseCase.php`
- Add `SourceRepositoryPort` constructor dependency
- Add `string $userId` to `execute(sourceId)` → `execute(sourceId, userId)`
- Verify ownership, throw `SourceNotFoundException` if null

### `GetEventDetailUseCase` — `src/Application/UseCase/Event/GetEventDetailUseCase.php`
- Add `SourceRepositoryPort` constructor dependency
- Add `string $userId` to `execute(eventId)` → `execute(eventId, userId)`
- After loading event (if not null), check `sourceRepository->findById(event->getSourceId(), userId)` → null → return null (controller treats null as 404, no info disclosure)

## Controller changes (Infrastructure Layer)
All 5 controllers already extract `$user->getId()` — just pass it to the use case:

- `ListEndpointsController` — pass `userId`, catch `SourceNotFoundException` → 404
- `CreateEndpointController` — pass `userId` as 4th arg, catch `SourceNotFoundException` → 404
- `DeleteEndpointController` — pass `userId`, catch `SourceNotFoundException` → 404 (alongside existing `EndpointNotFoundException`)
- `ListEventsController` — pass `userId`, catch `SourceNotFoundException` → 404
- `GetEventDetailController` — pass `userId` (null return already handled as 404)

## Test changes

### Update existing tests
- `AddEndpointUseCaseTest` — inject `SourceRepositoryPort` mock, add `userId` arg, add test for SourceNotFoundException when source not found
- `ListEndpointsUseCaseTest` — same pattern
- `DeleteEndpointUseCaseTest` — inject both mocks, update execute() calls, add ownership and not-found tests

### New test files
- `tests/Unit/Application/UseCase/Event/ListEventsUseCaseTest.php`
- `tests/Unit/Application/UseCase/Event/GetEventDetailUseCaseTest.php`

## Verification
```bash
php bin/phpunit
cd frontend && npx tsc --noEmit
```
