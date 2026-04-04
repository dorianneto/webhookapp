# Phase 5.1 — Event Ingestion: Domain & Application Layer

## Context
Phase 4 completed endpoint management. Phase 5.1 adds the event ingestion pipeline's domain and application layer: the ports and `IngestEventUseCase` that ties together source lookup, event persistence, delivery row creation, and queue dispatch. Phase 5.2 will wire these to Doctrine and Messenger.

---

## Key design decisions

### DeliverEventMessage placement
TASKS.md lists `DeliverEventMessage` under 5.2.3 (infrastructure), but `DeliveryQueuePort` in 5.1.2 must reference it. Since the port lives in the Application layer and the DTO has no infrastructure dependencies, `DeliverEventMessage` is created here in Phase 5.1 at `src/Application/Message/DeliverEventMessage.php`. Phase 5.2 wires the Messenger handler to it.

### SourceRepositoryPort extension
`IngestEventUseCase` looks up a source by its `inbound_uuid`. The current `SourceRepositoryPort` has no such method — `findByInboundUuid(string $inboundUuid): ?Source` must be added. This also requires updating `DoctrineSourceRepository` to implement the new method (a Phase 5.2 concern, but the interface change happens here).

### EventEndpointDeliveryRepositoryPort
Phase 6.1.4 defines the full port. Phase 5.1 only needs `save(EventEndpointDelivery)`. A minimal port is created now; Phase 6 extends it with `findOrCreate`, `updateStatus`, `findAllByEvent`.

---

## Files to create

### 1. `src/Application/Message/DeliverEventMessage.php`
```php
final readonly class DeliverEventMessage {
    public function __construct(
        public string $eventId,
        public string $endpointId,
        public int $attemptNumber,
    ) {}
}
```

### 2. `src/Application/Port/EventRepositoryPort.php`
```php
interface EventRepositoryPort {
    public function save(Event $event): void;
    public function findById(string $id): ?Event;
    /** @return Event[] */
    public function findRecentBySource(string $sourceId, int $limit): array;
    public function updateStatus(string $id, EventStatus $status): void;
}
```

### 3. `src/Application/Port/EventEndpointDeliveryRepositoryPort.php`
```php
interface EventEndpointDeliveryRepositoryPort {
    public function save(EventEndpointDelivery $delivery): void;
}
```
> Phase 6 will add `findOrCreate`, `updateStatus`, `findAllByEvent` to this interface.

### 4. `src/Application/Port/DeliveryQueuePort.php`
```php
interface DeliveryQueuePort {
    public function enqueue(DeliverEventMessage $message): void;
}
```

### 5. `src/Application/UseCase/Event/IngestEventUseCase.php`
Constructor injects: `SourceRepositoryPort`, `EventRepositoryPort`, `EndpointRepositoryPort`, `EventEndpointDeliveryRepositoryPort`, `DeliveryQueuePort`

`execute(string $eventId, string $inboundUuid, string $method, array $headers, string $body): void`

Steps:
1. `$source = $sourceRepository->findByInboundUuid($inboundUuid)` → throw `SourceNotFoundException` if null
2. Create `new Event($eventId, $source->getId(), $method, $headers, $body, EventStatus::Pending, new \DateTimeImmutable())`
3. `$eventRepository->save($event)`
4. `$endpoints = $endpointRepository->findActiveBySource($source->getId())`
5. For each endpoint:
   - Create `new EventEndpointDelivery(Uuid::v7()->toRfc4122(), $eventId, $endpoint->getId(), EventStatus::Pending, new \DateTimeImmutable(), new \DateTimeImmutable())`
   - `$deliveryRepository->save($delivery)`
   - `$queue->enqueue(new DeliverEventMessage($eventId, $endpoint->getId(), 1))`

### 6. Tests: `tests/Unit/Application/UseCase/Event/IngestEventUseCaseTest.php`

- `testSourceNotFoundThrowsException` — `findByInboundUuid` returns null → `SourceNotFoundException` thrown, `save` never called
- `testNoActiveEndpointsStillSavesEvent` — source found, `findActiveBySource` returns `[]` → event saved once, no deliveries or enqueue calls
- `testMultipleEndpointsEnqueuesAll` — source found, 2 endpoints → event saved once, 2 delivery rows saved, 2 messages enqueued (assert `enqueue` called twice with correct `eventId` and `attemptNumber=1`)

## Files to modify

### `src/Application/Port/SourceRepositoryPort.php`
Add method:
```php
public function findByInboundUuid(string $inboundUuid): ?Source;
```

---

## Files NOT touched
- All Domain entities (`Event`, `EventEndpointDelivery`, `EventStatus`) — already complete from Phase 1
- Infrastructure (`DoctrineSourceRepository` needs `findByInboundUuid` impl) — Phase 5.2 concern

---

## Verification
```bash
php bin/phpunit tests/Unit/Application/UseCase/Event/
```
All 3+ tests should pass. Also run the full suite to confirm the interface change doesn't break existing tests:
```bash
php bin/phpunit
```
