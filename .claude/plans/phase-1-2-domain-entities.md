# Plan: Phase 1.2 — Domain Entities

## Context
The project uses hexagonal architecture. Domain entities must be pure PHP classes with **no Symfony or Doctrine imports**. They live under `src/Domain/` (namespace `App\Domain\`). The `src/Domain/` directory does not exist yet.

A status enum `EventStatus` will be shared by `Event` and `EventEndpointDelivery`.

---

## Tasks (sequential)

### 1.2.1 — `App\Domain\User`
File: `src/Domain/User.php`
Fields: `string $id`, `string $email`, `string $passwordHash`, `\DateTimeImmutable $createdAt`

### 1.2.2 — `App\Domain\Source`
File: `src/Domain/Source.php`
Fields: `string $id`, `string $userId`, `string $name`, `string $inboundUuid`, `\DateTimeImmutable $createdAt`

### 1.2.3 — `App\Domain\Endpoint`
File: `src/Domain/Endpoint.php`
Fields: `string $id`, `string $sourceId`, `string $url`, `\DateTimeImmutable $createdAt`

### 1.2.4 — `App\Domain\Event`
File: `src/Domain/Event.php`
Fields: `string $id`, `string $sourceId`, `string $method`, `array $headers`, `string $body`, `EventStatus $status`, `\DateTimeImmutable $receivedAt`

### 1.2.5 — `App\Domain\EventEndpointDelivery`
File: `src/Domain/EventEndpointDelivery.php`
Fields: `string $id`, `string $eventId`, `string $endpointId`, `EventStatus $status`, `\DateTimeImmutable $createdAt`, `\DateTimeImmutable $updatedAt`

### 1.2.6 — `App\Domain\DeliveryAttempt`
File: `src/Domain/DeliveryAttempt.php`
Fields: `string $id`, `string $eventId`, `string $endpointId`, `int $attemptNumber`, `?int $statusCode`, `string $responseBody`, `int $durationMs`, `\DateTimeImmutable $attemptedAt`

### Supporting enum
File: `src/Domain/EventStatus.php`
Cases: `Pending`, `Delivered`, `Failed`

---

## Approach
- Each class uses a constructor with all fields as parameters (no setters needed at domain layer).
- Getters for all fields.
- `EventStatus` is a PHP 8.1 backed enum with string values `pending`, `delivered`, `failed`.
- No framework imports anywhere in `src/Domain/`.
- After each file is created, check off the corresponding task in `TASKS.md`.

## Verification
- `php bin/console cache:clear` — should succeed with no autoload errors
- `php bin/phpunit` — no failures from domain classes (no tests yet at this stage, but autoload must resolve)
