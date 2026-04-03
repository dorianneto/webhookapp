# Phase 4.2 — Endpoint Infrastructure

## Context
Phase 4.1 defines the port and use cases. This phase wires them to Doctrine (repository) and HTTP (controllers), following the exact same patterns as Phase 3.2 source infrastructure.

## Files to Create

### 1. Doctrine repository
`src/Infrastructure/Persistence/DoctrineEndpointRepository.php`

- `implements EndpointRepositoryPort`
- Inject `EntityManagerInterface`
- `save()` → `Entity\Endpoint::fromDomain()`, persist, flush
- `findById(string $id)` → `findOneBy(['id' => $id])`, return `->toDomain()` or null
- `findAllBySource(string $sourceId)` → `findBy(['sourceId' => $sourceId])`, map `toDomain()`
- `findActiveBySource(string $sourceId)` → same as `findAllBySource` for now (no `active` column yet)
- `delete(string $id)` → `findOneBy(['id' => $id])`; throw `EndpointNotFoundException` if null; remove + flush

### 2. Controllers (namespace `App\Controller\Api\v1\Endpoint\`)

**`ListEndpointsController.php`**
- Route: `GET /api/v1/sources/{sourceId}/endpoints`, name `list_endpoints`
- Auth guard (return 401 if not `User`)
- Call `ListEndpointsUseCase::execute($sourceId)` 
- Return `200` JSON array: `[{id, sourceId, url, createdAt}]`

**`CreateEndpointController.php`**
- Route: `POST /api/v1/sources/{sourceId}/endpoints`, name `create_endpoint`
- Auth guard
- Parse JSON body; validate `url` field with `Assert\NotBlank` + `Assert\Url` (Symfony validator)
- Generate `Uuid::v7()->toRfc4122()` for id
- Call `AddEndpointUseCase::execute($id, $sourceId, $url)`
- Catch `\InvalidArgumentException` → return `422` with `{'error': '...'}`
- Return `201` JSON: `{id, sourceId, url, createdAt}`

**`DeleteEndpointController.php`**
- Route: `DELETE /api/v1/endpoints/{id}`, name `delete_endpoint`
- Auth guard
- Call `DeleteEndpointUseCase::execute($id)`
- Catch `EndpointNotFoundException` → return `404`
- Return `204` no content

## Files to Modify
- None (Symfony autowiring handles dependency injection automatically via constructor types)

## Key Patterns (from Phase 3.2)
- Controllers use `$this->security->getUser()` and check `instanceof User` (the Entity User, `App\Entity\User`)
- JSON response field naming: camelCase (`sourceId`, `createdAt`)
- `createdAt` formatted as `\DateTimeInterface::ATOM`
- No explicit service registration needed — Symfony autowires by constructor type hints

## Verification
```bash
# Start app (if not running)
docker compose up -d

# List endpoints for a source
curl -s -b cookies.txt http://localhost:8080/api/v1/sources/{sourceId}/endpoints

# Create an endpoint
curl -s -b cookies.txt -X POST http://localhost:8080/api/v1/sources/{sourceId}/endpoints \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://example.com/hook"}'

# Delete an endpoint
curl -s -b cookies.txt -X DELETE http://localhost:8080/api/v1/endpoints/{id}
```
Expected: 200/201/204 responses with correct JSON shapes.
