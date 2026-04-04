# Phase 7 — Dashboard (Events & Delivery Attempts)

## Context
Phases 5–6 built event ingestion and delivery. Phase 7 exposes events in the UI: a list in `SourceDetailPage` (replacing the "Phase 7" placeholder) and a detail page with headers, body, and per-endpoint delivery attempt rows.

---

## 7.1 Domain & Application Layer

### New method needed on `DeliveryAttemptRepositoryPort`
`GetEventDetailUseCase` needs all attempts per event+endpoint pair. Add:
```php
/** @return DeliveryAttempt[] */
public function findAllByEventAndEndpoint(string $eventId, string $endpointId): array;
```
Also implement in `DoctrineDeliveryAttemptRepository` using `findBy(['eventId' => ..., 'endpointId' => ...], ['attemptNumber' => 'ASC'])`.

### New value objects (in `src/Application/Value/`)

**`EndpointDeliveryDetail.php`**
```php
final readonly class EndpointDeliveryDetail {
    public function __construct(
        public EventEndpointDelivery $delivery,
        public Endpoint $endpoint,
        /** @var DeliveryAttempt[] */
        public array $attempts,
    ) {}
}
```

**`EventDetail.php`**
```php
final readonly class EventDetail {
    public function __construct(
        public Event $event,
        /** @var EndpointDeliveryDetail[] */
        public array $deliveries,
    ) {}
}
```

### `ListEventsUseCase` (`src/Application/UseCase/Event/ListEventsUseCase.php`)
- Constructor: `EventRepositoryPort`
- `execute(string $sourceId): array` → delegates to `findRecentBySource($sourceId, 100)`

### `GetEventDetailUseCase` (`src/Application/UseCase/Event/GetEventDetailUseCase.php`)
- Constructor: `EventRepositoryPort`, `EventEndpointDeliveryRepositoryPort`, `EndpointRepositoryPort`, `DeliveryAttemptRepositoryPort`
- `execute(string $eventId): EventDetail`
  1. `$event = eventRepository->findById($eventId)` (return null-safe, controller handles 404)
  2. `$deliveries = deliveryRepository->findAllByEvent($eventId)`
  3. For each delivery: load `$endpoint = endpointRepository->findById($delivery->getEndpointId())`, load `$attempts = attemptRepository->findAllByEventAndEndpoint($eventId, $delivery->getEndpointId())`
  4. Return `new EventDetail($event, [new EndpointDeliveryDetail($delivery, $endpoint, $attempts), ...])`

---

## 7.2 Infrastructure

### `ListEventsController` (`src/Controller/Api/v1/Event/ListEventsController.php`)
- Route: `GET /api/v1/sources/{sourceId}/events`, name `list_events`
- Auth guard
- Call `ListEventsUseCase::execute($sourceId)`
- Return 200 JSON array:
```json
[{ "id": "...", "method": "POST", "status": "pending", "receivedAt": "..." }]
```

### `GetEventDetailController` (`src/Controller/Api/v1/Event/GetEventDetailController.php`)
- Route: `GET /api/v1/events/{id}`, name `get_event_detail`
- Auth guard
- Call `GetEventDetailUseCase::execute($id)` — return 404 if result is null
- Return 200 JSON:
```json
{
  "id": "...", "method": "POST", "headers": {}, "body": "...",
  "status": "delivered", "receivedAt": "...",
  "deliveries": [{
    "endpointId": "...", "endpointUrl": "https://...", "status": "delivered",
    "attempts": [{ "attemptNumber": 1, "statusCode": 200, "responseBody": "...", "durationMs": 120, "attemptedAt": "..." }]
  }]
}
```

---

## 7.3 Frontend

### Update `frontend/src/pages/SourceDetailPage.tsx`
Replace the placeholder `<p>Events coming in Phase 7.</p>` with:
- Fetch `/api/v1/sources/${sourceId}/events` in the initial `Promise.all`
- Show event list table: receivedAt | method | status badge (color-coded) | link to detail
- "Refresh" button that re-fetches events
- Empty state: "No events received yet."
- Status badge colors: pending=gray, delivered=green, failed=red

### New `frontend/src/pages/EventDetailPage.tsx`
Route: `/sources/:sourceId/events/:eventId`
- Fetch `GET /api/v1/events/${eventId}` on mount
- Header: "Event {method}" + back link to `/sources/${sourceId}`
- **Headers panel**: `<pre>` showing `JSON.stringify(event.headers, null, 2)`
- **Body panel**: `<pre>` showing event body
- **Deliveries section**: for each delivery:
  - Endpoint URL + overall status badge
  - Table of attempts: attempt# | status code | duration | response body | timestamp

### Update `frontend/src/App.tsx`
Add route before the catch-all:
```tsx
<Route path="/sources/:sourceId/events/:eventId"
  element={<ProtectedRoute><EventDetailPage /></ProtectedRoute>} />
```
> Insert before `/sources/:sourceId` to avoid any ambiguity.

---

## Files to create
| File | Purpose |
|---|---|
| `src/Application/Value/EndpointDeliveryDetail.php` | DTO for use case response |
| `src/Application/Value/EventDetail.php` | DTO for use case response |
| `src/Application/UseCase/Event/ListEventsUseCase.php` | delegates to findRecentBySource |
| `src/Application/UseCase/Event/GetEventDetailUseCase.php` | loads event + deliveries + endpoints + attempts |
| `src/Controller/Api/v1/Event/ListEventsController.php` | GET /api/v1/sources/{sourceId}/events |
| `src/Controller/Api/v1/Event/GetEventDetailController.php` | GET /api/v1/events/{id} |
| `frontend/src/pages/EventDetailPage.tsx` | event detail page |

## Files to modify
| File | Change |
|---|---|
| `src/Application/Port/DeliveryAttemptRepositoryPort.php` | Add `findAllByEventAndEndpoint` |
| `src/Infrastructure/Persistence/DoctrineDeliveryAttemptRepository.php` | Implement `findAllByEventAndEndpoint` |
| `frontend/src/pages/SourceDetailPage.tsx` | Replace placeholder with live event list |
| `frontend/src/App.tsx` | Add event detail route |
| `TASKS.md` | Check off 7.1.1, 7.1.2, 7.2.1, 7.2.2, 7.3.1, 7.3.2 |

## Verification
```bash
php bin/console debug:router | grep event
php bin/phpunit
```
TypeScript: `npx tsc --noEmit` in `frontend/`.
Manual: ingest a webhook, check event list on source detail page, click through to event detail.
