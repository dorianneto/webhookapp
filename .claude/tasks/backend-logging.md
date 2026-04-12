# Tasks: Backend Logging

**PRD:** `.claude/prd/backend-logging.md`
**Status legend:** `[ ]` todo · `[x]` done · `[-]` blocked

---

## Task 1 — Add `app` Monolog channel with stream handler

**File:** `config/packages/monolog.yaml`

- [x] Declare `app` in the top-level `channels` list
- [x] `when@dev`: add a `stream` handler for the `app` channel
  - type: `stream`
  - path: `%kernel.logs_dir%/%kernel.environment%.log`
  - level: `DEBUG`
  - channels: `[app]`
- [x] `when@prod`: add the `app` channel to the existing stderr handler (or add a dedicated one)
  - type: `stream`
  - path: `php://stderr`
  - level: `INFO`
  - formatter: `monolog.formatter.json`
  - channels: `[app]`
- [x] Verify the `app` channel does not bleed into the `main` handler (exclude it or use `channels: [app]` type: `inclusive`)

**Acceptance:** `bin/console debug:config monolog` shows the `app` channel and its handlers.

---

## Task 2 — Create `RequestIdSubscriber`

**File:** `src/EventSubscriber/RequestIdSubscriber.php`

- [ ] Implement `EventSubscriberInterface`
- [ ] Subscribe to `KernelEvents::REQUEST` at priority `100`
  - Read `X-Request-Id` from the incoming request headers
  - If absent, generate a UUIDv4 (`Symfony\Component\Uid\Uuid::v4()`)
  - Store on `$request->attributes->set('request_id', $id)`
- [ ] Subscribe to `KernelEvents::RESPONSE`
  - Echo the ID back as `X-Request-Id` response header
- [ ] Register service (autowiring should handle it; confirm in `config/services.yaml` if needed)

**Acceptance:** Any request to `/api/v1/*` or `/in/*` returns an `X-Request-Id` header; repeated requests with the same header echo it back unchanged.

---

## Task 3 — Add `requestId` to `DeliverEventMessage`

**Files:**
- `src/Application/Message/DeliverEventMessage.php`
- `src/Application/UseCase/Event/IngestEventUseCase.php`
- `src/Infrastructure/Messaging/DeliverEventMessageHandler.php`
- `src/Application/UseCase/Event/ProcessDeliveryUseCase.php`

- [ ] Add `readonly string $requestId` constructor argument to `DeliverEventMessage`
- [ ] In `IngestEventUseCase::execute()`: accept `string $requestId` as a new parameter; pass it when constructing `DeliverEventMessage`
- [ ] Update `IngestEventController` to read `$request->attributes->get('request_id')` and forward it to the use case
- [ ] In `ProcessDeliveryUseCase::execute()`: accept `string $requestId` as a new parameter
- [ ] In `DeliverEventMessageHandler::__invoke()`: pass `$message->requestId` to `ProcessDeliveryUseCase::execute()`

**Acceptance:** A `DeliverEventMessage` serialized to the queue includes the `requestId`; the handler passes it through to the use case unchanged.

---

## Task 4 — Add `requestId` parameter to CRUD use case signatures

**Files (all `execute()` signatures):**
- `src/Application/UseCase/Source/CreateSourceUseCase.php`
- `src/Application/UseCase/Source/ListSourcesUseCase.php`
- `src/Application/UseCase/Source/DeleteSourceUseCase.php`
- `src/Application/UseCase/Endpoint/AddEndpointUseCase.php`
- `src/Application/UseCase/Endpoint/ListEndpointsUseCase.php`
- `src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php`
- `src/Application/UseCase/Event/ListEventsUseCase.php`
- `src/Application/UseCase/Event/GetEventDetailUseCase.php`
- `src/Application/UseCase/RegisterUserUseCase.php`

- [ ] Add `string $requestId` as the first parameter of each `execute()` method
- [ ] Update every call site in the corresponding controller to pass `$request->attributes->get('request_id')`

**Note:** This task has no logging yet — it purely threads the ID through so Task 5 and Task 6 have it available. Keep the diff minimal.

---

## Task 5 — Logger injection and logging in `IngestEventUseCase`

**File:** `src/Application/UseCase/Event/IngestEventUseCase.php`

- [ ] Inject `Psr\Log\LoggerInterface $logger` with `#[Autowire(service: 'monolog.logger.app')]`
- [ ] Log: source lookup started (`DEBUG`, `request_id`, `source_uuid`)
- [ ] Log: source not found (`INFO`, `request_id`, `source_uuid`)
- [ ] Log: event created and saved (`DEBUG`, `request_id`, `event_id`, `source_id`)
- [ ] Log: no active endpoints found (`INFO`, `request_id`, `event_id`, `source_id`)
- [ ] Log: message enqueued per endpoint (`DEBUG`, `request_id`, `event_id`, `endpoint_id`)
- [ ] Log: ingest complete (`INFO`, `request_id`, `event_id`, `source_id`, `enqueued_count`)

---

## Task 6 — Logger injection and logging in `ProcessDeliveryUseCase`

**File:** `src/Application/UseCase/Event/ProcessDeliveryUseCase.php`

- [ ] Inject `Psr\Log\LoggerInterface $logger` with `#[Autowire(service: 'monolog.logger.app')]`
- [ ] Log: delivery attempt started (`INFO`, `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `endpoint_url`)
- [ ] Log: HTTP delivery succeeded (`INFO`, `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `status_code`, `duration_ms`)
- [ ] Log: HTTP delivery failed — non-2xx (`WARNING`, `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `status_code`, `duration_ms`)
- [ ] Log: HTTP transport exception — no response (`WARNING`, `request_id`, `event_id`, `endpoint_id`, `attempt_number`, `exception_message`)
- [ ] Log: delivery marked `Failed` after max attempts (`ERROR`, `request_id`, `event_id`, `endpoint_id`, `attempt_number`)
- [ ] Log: retry enqueued (`INFO`, `request_id`, `event_id`, `endpoint_id`, `next_attempt`, `delay_ms`)
- [ ] Log: event status recomputed (`DEBUG`, `request_id`, `event_id`, `new_status`)

---

## Task 7 — Logger injection and logging in `DeliverEventMessageHandler`

**File:** `src/Infrastructure/Messaging/DeliverEventMessageHandler.php`

- [ ] Inject `Psr\Log\LoggerInterface $logger` with `#[Autowire(service: 'monolog.logger.app')]`
- [ ] Log: message received from queue (`INFO`, `request_id`, `event_id`, `endpoint_id`, `attempt_number`)
- [ ] Log: processing complete (`INFO`, `request_id`, `event_id`, `endpoint_id`)
- [ ] Log: unhandled exception from use case (`ERROR`, `request_id`, `event_id`, `endpoint_id`, `exception_class`, `message`, `trace`)

---

## Task 8 — Logger injection and logging in `IngestEventController`

**File:** `src/Controller/Api/v1/Event/IngestEventController.php`

- [ ] Inject `Psr\Log\LoggerInterface $logger` with `#[Autowire(service: 'monolog.logger.app')]`
- [ ] Log: ingest request received (`INFO`, `request_id`, `source_uuid`, `method`, `body_bytes`)
- [ ] Log: source not found — 404 (`INFO`, `request_id`, `source_uuid`)
- [ ] Log: ingest complete (`INFO`, `request_id`, `event_id`, `source_id`, `endpoint_count`)

---

## Task 9 — Logger injection and logging in API controllers (CRUD)

One sub-task per controller. Each follows the same pattern: inject logger, log entry + success + domain exceptions.

### 9a — `RegistrationController`
**File:** `src/Controller/Api/v1/RegistrationController.php`
- [ ] Inject logger
- [ ] Log: request received (`INFO`, `request_id`, `route`)
- [ ] Log: validation failure (`WARNING`, `request_id`, `violations`)
- [ ] Log: email already taken (`INFO`, `request_id`, `route`, `exception_class`)
- [ ] Log: user registered (`INFO`, `request_id`, `route`, `http_status`)

### 9b — `CreateSourceController`
**File:** `src/Controller/Api/v1/Source/CreateSourceController.php`
- [ ] Inject logger
- [ ] Log: request received, validation failure, success

### 9c — `ListSourcesController`
**File:** `src/Controller/Api/v1/Source/ListSourcesController.php`
- [ ] Inject logger
- [ ] Log: request received, success

### 9d — `DeleteSourceController`
**File:** `src/Controller/Api/v1/Source/DeleteSourceController.php`
- [ ] Inject logger
- [ ] Log: request received, not found, success

### 9e — `CreateEndpointController`
**File:** `src/Controller/Api/v1/Endpoint/CreateEndpointController.php`
- [ ] Inject logger
- [ ] Log: request received, validation failure (invalid URL, source not found), success

### 9f — `ListEndpointsController`
**File:** `src/Controller/Api/v1/Endpoint/ListEndpointsController.php`
- [ ] Inject logger
- [ ] Log: request received, success

### 9g — `DeleteEndpointController`
**File:** `src/Controller/Api/v1/Endpoint/DeleteEndpointController.php`
- [ ] Inject logger
- [ ] Log: request received, not found, success

### 9h — `ListEventsController`
**File:** `src/Controller/Api/v1/Event/ListEventsController.php`
- [ ] Inject logger
- [ ] Log: request received, source not found, success

### 9i — `GetEventDetailController`
**File:** `src/Controller/Api/v1/Event/GetEventDetailController.php`
- [ ] Inject logger
- [ ] Log: request received, event not found, success

### 9j — `MeController`
**File:** `src/Controller/Api/v1/MeController.php`
- [ ] Inject logger
- [ ] Log: request received, success

---

## Task 10 — Logger injection and logging in CRUD use cases

One sub-task per use case. Entry + success + domain exception per use case.

### 10a — `RegisterUserUseCase`
- [ ] Inject logger, log: attempt, email taken, registered

### 10b — `CreateSourceUseCase`
- [ ] Inject logger, log: attempt, created

### 10c — `ListSourcesUseCase`
- [ ] Inject logger, log: attempt, returned count

### 10d — `DeleteSourceUseCase`
- [ ] Inject logger, log: attempt, not found, deleted

### 10e — `AddEndpointUseCase`
- [ ] Inject logger, log: attempt, source not found / invalid URL, added

### 10f — `ListEndpointsUseCase`
- [ ] Inject logger, log: attempt, returned count

### 10g — `DeleteEndpointUseCase`
- [ ] Inject logger, log: attempt, not found, deleted

### 10h — `ListEventsUseCase`
- [ ] Inject logger, log: attempt, returned count

### 10i — `GetEventDetailUseCase`
- [ ] Inject logger, log: attempt, not found, returned

---

## Task 11 — Verify existing tests still pass

- [ ] Run `php bin/phpunit` inside the app container — all tests green
- [ ] Spot-check that no test breaks due to changed `execute()` signatures (mock expectations, constructor changes)

---

## Execution order

```
Task 1  (config)
  └─ Task 2  (subscriber — no dependencies)
       └─ Task 3  (message shape + IngestEventUseCase requestId wiring)
            └─ Task 4  (thread requestId through all other use cases)
                 ├─ Task 5  (IngestEventUseCase logging)
                 ├─ Task 6  (ProcessDeliveryUseCase logging)
                 ├─ Task 7  (handler logging)
                 ├─ Task 8  (IngestEventController logging)
                 └─ Task 9 + 10  (CRUD controllers + use cases — parallelizable)
                      └─ Task 11 (test run)
```

Tasks 5–10 can be worked in parallel once Task 4 is done.
