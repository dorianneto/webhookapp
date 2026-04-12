# PRD: Backend Logging

**Status:** Draft
**Date:** 2026-04-11
**Scope:** PHP backend — controllers, use cases, and the ingest/delivery pipeline

---

## Problem

The backend has zero application-level logging. In production, when a webhook delivery silently fails, a use case throws unexpectedly, or the ingest pipeline drops an event, there is no log trail to reconstruct what happened. The only visibility available is the HTTP response code returned to the caller — nothing captures the internal decision path, timing, or context.

This makes debugging production incidents reactive and slow: the data needed to answer "why did this event fail?" simply does not exist.

---

## Goal

Add structured, request-scoped logging throughout controllers and use cases so that every meaningful action — from the moment a request enters the system to the moment it resolves — produces a traceable log entry. The ingest and delivery pipeline (the highest-value and most complex path) is the primary focus.

---

## Non-Goals

- No log aggregation, alerting, or dashboard (out of scope for this iteration).
- No distributed tracing (e.g. OpenTelemetry spans).
- No frontend logging.
- No changes to the existing Monolog channel or handler configuration beyond adding a stream handler for the `hookyard` channel.
- No logging in Doctrine entities, repositories, or infrastructure adapters (except the HTTP delivery adapter, which is in scope).

---

## Requirements

### R1 — Monolog stream handler for the `hookyard` channel

Add a dedicated `app` Monolog channel backed by a `stream` handler. All application logs must go through this channel, keeping them separate from the Symfony framework channel.

- **Dev:** stream to `%kernel.logs_dir%/%kernel.environment%.log`, level `DEBUG`.
- **Prod:** stream to `php://stderr`, level `INFO`, formatter `monolog.formatter.json` (already configured).

The channel is declared in `config/packages/monolog.yaml`. No new packages are needed — Monolog is already a Symfony default.

### R2 — Correlation ID

Every HTTP request must carry a `X-Request-Id` header that propagates through the entire sync and async pipeline. If the header is absent on an incoming request, generate a UUIDv4 and attach it.

- A Symfony event subscriber (`RequestIdSubscriber`) reads/generates the ID on `kernel.request` and stores it in a request attribute.
- All log entries must include a `request_id` field.
- For async worker messages, the `DeliverEventMessage` must carry the `requestId` that was present when the message was enqueued so the worker can include it in its log entries.

### R3 — Controller logging

Every invokable controller logs:

| When | Level | Fields |
|---|---|---|
| Request received | `INFO` | `request_id`, `route`, `method`, `user_id` (if authenticated) |
| Validation failure | `WARNING` | `request_id`, `route`, `violations` |
| Use case throws a domain exception (4xx) | `INFO` | `request_id`, `route`, `exception_class`, `message` |
| Unhandled exception (5xx) | `ERROR` | `request_id`, `route`, `exception_class`, `message`, `trace` |
| Successful response dispatched | `INFO` | `request_id`, `route`, `http_status` |

**Ingest controller** (`IngestEventController`) additionally logs:

| When | Level | Fields |
|---|---|---|
| Ingest request received | `INFO` | `request_id`, `source_uuid`, `method`, `body_bytes` |
| Source not found | `INFO` | `request_id`, `source_uuid` |
| Ingest complete (enqueued N messages) | `INFO` | `request_id`, `event_id`, `source_id`, `endpoint_count` |

### R4 — Use case logging

#### `IngestEventUseCase`

| When | Level | Fields |
|---|---|---|
| Source lookup | `DEBUG` | `request_id`, `source_uuid` |
| Source not found | `INFO` | `request_id`, `source_uuid` |
| Event created and saved | `DEBUG` | `request_id`, `event_id`, `source_id` |
| No active endpoints found | `INFO` | `request_id`, `event_id`, `source_id` |
| Message enqueued per endpoint | `INFO` | `request_id`, `event_id`, `endpoint_id` |
| Ingest complete | `INFO` | `request_id`, `event_id`, `source_id`, `enqueued_count` |

#### `ProcessDeliveryUseCase`

| When | Level | Fields |
|---|---|---|
| Delivery attempt started | `INFO` | `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `endpoint_url` |
| HTTP delivery succeeded | `INFO` | `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `status_code`, `duration_ms` |
| HTTP delivery failed (non-2xx) | `WARNING` | `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `status_code`, `duration_ms` |
| HTTP transport exception (no response) | `WARNING` | `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `exception_message` |
| Delivery marked `Failed` (max attempts reached) | `ERROR` | `request_id`, `event_id`, `endpoint_id`, `attempt_number` |
| Retry enqueued | `INFO` | `request_id`, `event_id`, `endpoint_id`, `next_attempt`, `delay_ms` |
| Event status recomputed | `INFO` | `request_id`, `event_id`, `new_status` |

#### Other use cases (CRUD)

- Log at `INFO` on entry (operation + entity type + relevant IDs) and on success.
- Log at `INFO` when a domain exception is thrown (entity not found, ownership violation).
- No `DEBUG`-level tracing required for simple CRUD paths.

### R5 — Worker handler logging

`DeliverEventMessageHandler` logs:

| When | Level | Fields |
|---|---|---|
| Message received from queue | `INFO` | `request_id`, `event_id`, `endpoint_id`, `attempt_number` |
| Processing complete (delegates to use case) | `INFO` | `request_id`, `event_id`, `endpoint_id` |
| Unhandled exception from use case | `ERROR` | `request_id`, `event_id`, `endpoint_id`, `exception_class`, `message`, `trace` |

### R6 — Structured log format

All log entries must use structured context arrays (Monolog's second argument), never string interpolation for variable data. Example:

```php
// Correct
$this->logger->info('Ingest complete', [
    'request_id'     => $requestId,
    'event_id'       => (string) $event->getId(),
    'source_id'      => (string) $source->getId(),
    'enqueued_count' => $enqueuedCount,
]);

// Incorrect — do not do this
$this->logger->info("Ingest complete for event {$event->getId()}");
```

---

## Implementation Plan

### Step 1 — Monolog config

Update `config/packages/monolog.yaml`:
- Declare `app` as a new channel.
- Add a `stream` handler for `when@dev` (file, DEBUG) and update `when@prod` to route the `hookyard` channel to the existing stderr/JSON handler.

### Step 2 — Correlation ID subscriber

Create `src/EventSubscriber/RequestIdSubscriber.php`:
- On `kernel.request` (priority 100): read `X-Request-Id` header or generate UUIDv4.
- Store on `$request->attributes->set('request_id', $id)`.
- On `kernel.response`: echo the ID back as `X-Request-Id` response header.

Inject `RequestStack` into use cases and the message handler so they can read the ID at call time. Alternatively, pass `requestId` as an explicit parameter to use case `execute()` methods — this keeps the domain layer free of Symfony dependencies and is preferred per the hexagonal architecture rules.

### Step 3 — `DeliverEventMessage` carries `requestId`

Add a `requestId` field to `src/Application/Message/DeliverEventMessage.php`. `IngestEventUseCase` sets this field when enqueuing. `DeliverEventMessageHandler` passes it to `ProcessDeliveryUseCase`.

Use case `execute()` signatures gain a `string $requestId` parameter.

### Step 4 — Inject logger into controllers

Inject `LoggerInterface $logger` (with `#[Autowire(service: 'monolog.logger.hookyard')]`) into each controller. Add log calls per R3.

### Step 5 — Inject logger into use cases

Same injection pattern. Add log calls per R4. Pass `$requestId` through from the controller call site.

### Step 6 — Inject logger into the worker handler

Add log calls per R5.

---

## Acceptance Criteria

1. A `POST /in/{uuid}` request produces log entries covering: request received → source lookup → event saved → each endpoint enqueued → ingest complete.
2. The same `request_id` appears in all log entries from a single ingest request.
3. When the worker processes a `DeliverEventMessage`, log entries include the `request_id` from the originating ingest request.
4. A failed HTTP delivery (all 5 attempts exhausted) produces a log entry at `ERROR` level with `event_id` and `endpoint_id`.
5. All context values are in the structured context array (second argument), not embedded in the message string.
6. No log entry leaks sensitive data (passwords, API keys, full request bodies beyond `body_bytes`).
7. Existing unit tests continue to pass. New tests are not required for logging calls themselves.
8. The `hookyard` channel is isolated: Symfony framework noise does not appear in app-level log output.
