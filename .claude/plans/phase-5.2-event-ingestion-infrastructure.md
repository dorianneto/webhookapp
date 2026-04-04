# Phase 5.2 — Event Ingestion: Infrastructure

## Context
Phase 5.1 built the domain/application layer. Phase 5.2 wires it to Doctrine, Symfony Messenger, and HTTP. The `DeliverEventMessage` DTO (5.2.3) was already created in Phase 5.1 at `src/Application/Message/DeliverEventMessage.php`. Security is already correct: `security.yaml` has `/in/` as `PUBLIC_ACCESS` and SPA controller excludes `/in/*` paths.

---

## Files to create

### 1. `src/Infrastructure/Persistence/DoctrineEventRepository.php`
Implements `EventRepositoryPort`.

- `save(Event $event)` → `Entity\Event::fromDomain()`, persist, flush
- `findById(string $id)` → `findOneBy(['id' => $id])`, return `->toDomain()` or null
- `findRecentBySource(string $sourceId, int $limit)` → `findBy(['sourceId' => $sourceId], ['receivedAt' => 'DESC'], $limit)`, map `toDomain()`
- `updateStatus(string $id, EventStatus $status)` → load entity, call `$entity->setStatus()` (needs a setter on the entity - see below), flush

> `Entity\Event` has no `setStatus()` method yet. Add it during this phase.

### 2. `src/Infrastructure/Persistence/DoctrineEventEndpointDeliveryRepository.php`
Implements `EventEndpointDeliveryRepositoryPort` (Phase 6 will add more methods).

- `save(EventEndpointDelivery $delivery)` → `Entity\EventEndpointDelivery::fromDomain()`, persist, flush

### 3. `src/Infrastructure/Messaging/MessengerDeliveryQueue.php`
Implements `DeliveryQueuePort`.

```php
final class MessengerDeliveryQueue implements DeliveryQueuePort {
    public function __construct(private readonly MessageBusInterface $bus) {}
    public function enqueue(DeliverEventMessage $message): void {
        $this->bus->dispatch($message);
    }
}
```

### 4. `src/Controller/IngestEventController.php`
Route: `POST /in/{uuid}`, name `ingest_event`, no auth (already public in `security.yaml`).

```
execute(Request $request, string $uuid): JsonResponse
- $eventId = Uuid::v7()->toRfc4122()
- $body    = $request->getContent()
- $headers = $request->headers->all()
- $method  = $request->getMethod()
- call IngestEventUseCase::execute($eventId, $uuid, $method, $headers, $body)
- catch SourceNotFoundException → return 404
- return 200 OK (empty body)
```

## Files to modify

### `src/Infrastructure/Persistence/DoctrineSourceRepository.php`
Add `findByInboundUuid(string $inboundUuid): ?Source`:
```php
public function findByInboundUuid(string $inboundUuid): ?DomainSource
{
    $entity = $this->entityManager
        ->getRepository(SourceEntity::class)
        ->findOneBy(['inboundUuid' => $inboundUuid]);
    return $entity?->toDomain();
}
```

### `src/Entity/Event.php`
Add `setStatus(EventStatus $status): void` so `DoctrineEventRepository::updateStatus()` can mutate the entity before flushing.

### `config/packages/messenger.yaml`
Add routing for `DeliverEventMessage`:
```yaml
routing:
    App\Application\Message\DeliverEventMessage: async
```

### `TASKS.md`
Check off 5.2.1, 5.2.2, 5.2.3, 5.2.4.

---

## Verification
```bash
# Confirm container compiles and routes registered
php bin/console debug:router | grep in/
php bin/console debug:container | grep MessengerDeliveryQueue

# Full test suite should still pass (no new unit tests needed for infrastructure)
php bin/phpunit
```

Manual smoke test (requires running app + DB):
```bash
# Create a source first, copy its inboundUuid, then:
curl -s -X POST http://localhost:8080/in/<inboundUuid> \
  -H 'Content-Type: application/json' \
  -d '{"test": 1}'
# Expect: 200 OK
```
