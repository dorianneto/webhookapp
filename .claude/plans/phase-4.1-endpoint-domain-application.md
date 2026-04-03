# Phase 4.1 — Endpoint Domain & Application Layer

## Context
Phase 3 delivered source management (CRUD) following hexagonal architecture. Phase 4.1 extends this pattern to endpoints — the destination URLs that receive forwarded webhooks. The `Endpoint` domain entity and Doctrine entity already exist (from Phase 1), so this phase only adds the port interface, three use cases, and unit tests.

## Files to Create

### 1. Port interface
`src/Application/Port/EndpointRepositoryPort.php`
```php
interface EndpointRepositoryPort {
    public function save(Endpoint $endpoint): void;
    public function findById(string $id): ?Endpoint;
    public function findAllBySource(string $sourceId): array;
    public function delete(string $id): void;
    public function findActiveBySource(string $sourceId): array; // reserved for Phase 5
}
```
> Mirror `SourceRepositoryPort` style — no userId scoping needed here since ownership is validated at the source level by controllers.

### 2. Use cases

**`src/Application/UseCase/Endpoint/AddEndpointUseCase.php`**
- `execute(string $id, string $sourceId, string $url): Endpoint`
- Validates URL format (FILTER_VALIDATE_URL); throws `\InvalidArgumentException` on failure
- Constructs `Domain\Endpoint` with `new \DateTimeImmutable()` for `createdAt`
- Calls `$repository->save()`; returns the domain object

**`src/Application/UseCase/Endpoint/ListEndpointsUseCase.php`**
- `execute(string $sourceId): array`
- Delegates to `$repository->findAllBySource($sourceId)`

**`src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php`**
- `execute(string $id): void`
- Delegates to `$repository->delete($id)`
- Repository throws `EndpointNotFoundException` if not found

### 3. Domain exception
`src/Domain/Exception/EndpointNotFoundException.php`
```php
final class EndpointNotFoundException extends \RuntimeException {}
```

## Files to Modify
- None (domain `Endpoint` entity already exists at `src/Domain/Endpoint.php`)

## Tests to Create

`tests/Unit/Application/UseCase/Endpoint/AddEndpointUseCaseTest.php`
- `testExecuteSavesEndpointWithCorrectData` — assert `save()` called once with correct id/sourceId/url
- `testExecuteThrowsOnInvalidUrl` — assert `\InvalidArgumentException` thrown for bad URL
- `testExecuteReturnsEndpoint` — assert return value is `Endpoint` instance

`tests/Unit/Application/UseCase/Endpoint/ListEndpointsUseCaseTest.php`
- `testExecuteReturnsList` — stub returns array of 2 `Endpoint` objects, assert same array returned

`tests/Unit/Application/UseCase/Endpoint/DeleteEndpointUseCaseTest.php`
- `testExecuteCallsDelete` — assert `delete()` called once with correct id

Follow `CreateSourceUseCaseTest` as template: `createMock(EndpointRepositoryPort::class)`, constructor injection, `expects($this->once())`.

## Verification
```bash
php bin/phpunit tests/Unit/Application/UseCase/Endpoint/
```
All 6+ tests should pass with no errors.
