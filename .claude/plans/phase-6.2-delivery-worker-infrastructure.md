# Phase 6.2 — Event Delivery Worker: Infrastructure

## Context
Phase 6.1 built all ports and `ProcessDeliveryUseCase`. Phase 6.2 wires them: HTTP adapter, Doctrine repositories, transaction adapter, Messenger handler, and messenger config. `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0` is already in `.env`. `symfony/http-client` is already installed. The container is currently broken because `DoctrineEventEndpointDeliveryRepository` doesn't implement the 3 methods added to the port in Phase 6.1.

---

## Files to create

### 1. `src/Infrastructure/Http/SymfonyHttpDeliveryAdapter.php`
Implements `HttpDeliveryPort`. Injected via Symfony autowiring: `HttpClientInterface`.

```php
public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): DeliveryResult
{
    $start = hrtime(true);
    try {
        $response = $this->client->request('POST', $url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => $timeoutSeconds,
        ]);
        $statusCode   = $response->getStatusCode();
        $responseBody = mb_substr($response->getContent(false), 0, 500);
        $durationMs   = (int) ((hrtime(true) - $start) / 1_000_000);
        $success      = $statusCode >= 200 && $statusCode < 300;
        return new DeliveryResult($statusCode, $responseBody, $durationMs, $success);
    } catch (TransportExceptionInterface $e) {
        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        return new DeliveryResult(null, '', $durationMs, false);
    }
}
```

> `$response->getContent(false)` suppresses the exception on non-2xx so we can still capture the body. Truncation with `mb_substr` preserves valid UTF-8.

### 2. `src/Infrastructure/Transaction/DoctrineTransactionAdapter.php`
Implements `TransactionPort`.

```php
public function execute(callable $operation): void
{
    $this->entityManager->wrapInTransaction($operation);
}
```

### 3. `src/Infrastructure/Messaging/DeliverEventMessageHandler.php`
Symfony Messenger handler.

```php
#[AsMessageHandler]
final class DeliverEventMessageHandler
{
    public function __construct(private readonly ProcessDeliveryUseCase $useCase) {}
    public function __invoke(DeliverEventMessage $message): void
    {
        $this->useCase->execute($message);
    }
}
```

### 4. `src/Infrastructure/Persistence/DoctrineDeliveryAttemptRepository.php`
Implements `DeliveryAttemptRepositoryPort`.

- `save()` → `Entity\DeliveryAttempt::fromDomain()`, persist, flush
- `countByEventAndEndpoint()` → `count(findBy(['eventId' => ..., 'endpointId' => ...]))`

---

## Files to modify

### `src/Infrastructure/Persistence/DoctrineEventEndpointDeliveryRepository.php`
Add the 3 missing methods (fixes the broken container):

- `findByEventAndEndpoint(eventId, endpointId)` → `findOneBy(['eventId' => ..., 'endpointId' => ...])`, return `toDomain()` or null
- `updateStatus(id, status)` → load entity, set status + updatedAt via `setStatus()`, flush
- `findAllByEvent(eventId)` → `findBy(['eventId' => ...])`, map `toDomain()`

> `Entity\EventEndpointDelivery` needs `setStatus(EventStatus): void` — add it (like `Entity\Event::setStatus` added in Phase 5.2).

### `src/Entity/EventEndpointDelivery.php`
Add `setStatus(EventStatus $status): void` that updates `$this->status` and `$this->updatedAt`.

### `config/packages/messenger.yaml`
Disable auto-retries on `async` (retries are managed manually in `ProcessDeliveryUseCase`):
```yaml
async:
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    retry_strategy:
        max_retries: 0
```

---

## Verification
```bash
# Container compiles
php bin/console debug:container MessengerDeliveryQueue

# Message handler registered
php bin/console debug:messenger

# Full test suite
php bin/phpunit
```
