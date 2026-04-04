# Phase 6.1 — Event Delivery Worker: Domain & Application Layer

## Context
Phase 5 built event ingestion — events are persisted and delivery messages are enqueued. Phase 6.1 implements the worker side: the ports and `ProcessDeliveryUseCase` that picks up each `DeliverEventMessage`, makes the HTTP call, records the attempt, and updates delivery/event status atomically. Phase 6.2 wires Guzzle, Doctrine, and Messenger.

---

## Key Design Decisions

### Transaction atomicity via TransactionPort
CLAUDE.md requires updating `event_endpoint_deliveries.status` and recomputing `events.status` atomically in the same DB transaction. In hexagonal architecture this is solved with a `TransactionPort`:
```php
interface TransactionPort {
    public function execute(callable $operation): void;
}
```
The use case wraps the status update block in `$this->transaction->execute(fn() => ...)`. The Doctrine implementation wraps it in `$em->wrapInTransaction(...)`.

### DeliveryQueuePort extended with delay
The existing `DeliveryQueuePort::enqueue()` needs a `delayMs` parameter for retry scheduling. Default = 0 so `IngestEventUseCase` is unaffected.

### EventEndpointDeliveryRepositoryPort extended
Currently only has `save()`. Phase 6.1.4 adds `findByEventAndEndpoint`, `updateStatus`, `findAllByEvent`.

### Delivery status on retry
When an attempt fails and `attemptNumber < 5`, the `EventEndpointDelivery` status stays `Pending` (it will be retried). Only on attempt 5 failure does it become `Failed`. This means the event status computation correctly returns `Pending` during active retries.

### Retry delay map
| After attempt | Delay |
|---|---|
| 1 | 30 s |
| 2 | 5 m (300 s) |
| 3 | 30 m (1 800 s) |
| 4 | 2 h (7 200 s) |
| 5 | — (no retry) |

---

## Files to Create

### 1. `src/Application/Value/DeliveryResult.php`
```php
final readonly class DeliveryResult {
    public function __construct(
        public ?int $statusCode,
        public string $responseBody,
        public int $durationMs,
        public bool $success,  // true if statusCode is 2xx
    ) {}
}
```

### 2. `src/Application/Port/HttpDeliveryPort.php`
```php
interface HttpDeliveryPort {
    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): DeliveryResult;
}
```

### 3. `src/Application/Port/DeliveryAttemptRepositoryPort.php`
```php
interface DeliveryAttemptRepositoryPort {
    public function save(DeliveryAttempt $attempt): void;
    public function countByEventAndEndpoint(string $eventId, string $endpointId): int;
}
```

### 4. `src/Application/Port/TransactionPort.php`
```php
interface TransactionPort {
    public function execute(callable $operation): void;
}
```

### 5. `src/Application/UseCase/Event/ProcessDeliveryUseCase.php`
Constructor injects: `EventRepositoryPort`, `EventEndpointDeliveryRepositoryPort`, `EndpointRepositoryPort`, `DeliveryAttemptRepositoryPort`, `HttpDeliveryPort`, `DeliveryQueuePort`, `TransactionPort`

`execute(DeliverEventMessage $message): void`

1. Load `$endpoint = $endpointRepository->findById($message->endpointId)`
2. Load `$event = $eventRepository->findById($message->eventId)`
3. Load `$delivery = $deliveryRepository->findByEventAndEndpoint($message->eventId, $message->endpointId)`
4. Merge event headers + `['X-Webhook-Event-Id' => $message->eventId]`
5. `$result = $httpDelivery->deliver($endpoint->getUrl(), $headers, $event->getBody(), 10)`
6. Save `DeliveryAttempt` with result fields, `attemptNumber = $message->attemptNumber`
7. Compute new delivery status:
   - `success` → `Delivered`
   - `!success && attemptNumber >= 5` → `Failed`
   - `!success && attemptNumber < 5` → `Pending`
8. `$this->transaction->execute(fn() =>`:
   - `deliveryRepository->updateStatus($delivery->getId(), $newDeliveryStatus)`
   - `$all = deliveryRepository->findAllByEvent($message->eventId)`
   - `eventRepository->updateStatus($message->eventId, computeEventStatus($all))`
9. If `!success && attemptNumber < 5`:
   - `$delayMs = [30_000, 300_000, 1_800_000, 7_200_000][$message->attemptNumber - 1]`
   - `$queue->enqueue(new DeliverEventMessage($eventId, $endpointId, $attemptNumber + 1), $delayMs)`

`computeEventStatus(array $deliveries): EventStatus` (private):
- Any `Failed` → return `Failed`
- All `Delivered` → return `Delivered`
- Otherwise → return `Pending`

### 6. `tests/Unit/Application/UseCase/Event/ProcessDeliveryUseCaseTest.php`
Tests:
- `testSuccessOnFirstAttempt` — 2xx result → delivery=Delivered, event=Delivered, no re-enqueue
- `testFailureOnAttempt1ReEnqueuesWithDelay` — fail on attempt 1 → delivery stays Pending, re-enqueued with 30000ms
- `testFailureOnAttempt2ReEnqueuesWithLongerDelay` — fail on attempt 2 → 300000ms delay
- `testFailureOnAttempt5MarksDeliveryFailed` — fail on attempt 5 → delivery=Failed, no re-enqueue
- `testMixedDeliveriesEventStatusPending` — one Delivered, one Pending → event stays Pending
- `testAllDeliveredEventStatusDelivered` — all Delivered → event=Delivered
- `testAnyFailedEventStatusFailed` — one Failed → event=Failed

TransactionPort in tests: stub that executes the callable immediately.

---

## Files to Modify

### `src/Application/Port/EventEndpointDeliveryRepositoryPort.php`
Add:
```php
public function findByEventAndEndpoint(string $eventId, string $endpointId): ?EventEndpointDelivery;
public function updateStatus(string $id, EventStatus $status): void;
/** @return EventEndpointDelivery[] */
public function findAllByEvent(string $eventId): array;
```

### `src/Application/Port/DeliveryQueuePort.php`
Add `delayMs` parameter (default 0):
```php
public function enqueue(DeliverEventMessage $message, int $delayMs = 0): void;
```

### `src/Infrastructure/Messaging/MessengerDeliveryQueue.php`
Update to use `DelayStamp` when `$delayMs > 0`:
```php
public function enqueue(DeliverEventMessage $message, int $delayMs = 0): void {
    $stamps = $delayMs > 0 ? [new DelayStamp($delayMs)] : [];
    $this->bus->dispatch($message, $stamps);
}
```

---

## Files NOT touched
- All existing domain entities — complete from Phase 1
- `DoctrineEventEndpointDeliveryRepository` — only has `save()` now; Phase 6.2 extends it

---

## Verification
```bash
php bin/phpunit tests/Unit/Application/UseCase/Event/
php bin/phpunit
```
All existing 21 tests + new 7 tests = 28 tests should pass.
