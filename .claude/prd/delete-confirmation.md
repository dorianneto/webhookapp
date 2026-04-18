# PRD: Delete Confirmation Modal + Endpoint Cascade

## Overview

Replace all native `window.confirm()` delete dialogs with a custom modal that requires the user to type the record's name before confirming deletion. Additionally, when an endpoint is deleted, its related events (and their delivery records) must be deleted atomically in the backend.

---

## Problem

1. **UX**: Browser `window.confirm()` is visually inconsistent, bypasses the design system, and provides no friction for irreversible destructive actions.
2. **Data integrity**: Deleting an endpoint leaves orphaned event records. The `event_endpoint_deliveries` and `delivery_attempts` rows cascade correctly, but the `events` rows themselves remain in the DB with no active delivery target.

---

## Scope

### Frontend

Two deletion flows to update:
- `frontend/src/pages/DashboardPage.tsx` — Source deletion (line 35–45)
- `frontend/src/pages/SourceDetailPage.tsx` — Endpoint deletion (lines 102–112)

**What to build:**

1. **Install shadcn Dialog** — run `npx shadcn add dialog` to scaffold `src/components/ui/dialog.tsx` (Radix UI `Dialog` primitive, not yet in the project).

2. **Create `DeleteConfirmModal` component** at `frontend/src/components/DeleteConfirmModal.tsx`:
   - Props: `open`, `onOpenChange`, `resourceName` (the name to type), `resourceLabel` (e.g. "source" or "endpoint"), `onConfirm`, `isLoading`
   - UI: `Dialog` wrapper → title "Delete [resourceLabel]" → description explaining the action is irreversible → `Input` where user types the exact name → "Delete" `Button variant="destructive"` disabled until input matches `resourceName` exactly → "Cancel" `Button variant="outline"`
   - Use `cn()` from `src/lib/utils.ts` for conditional classes
   - Reset input state on `onOpenChange(false)`

3. **Update DashboardPage.tsx**: Replace `window.confirm()` with state (`deleteTarget: { id, name } | null`) that opens `DeleteConfirmModal`. On confirm, call the existing DELETE API and close modal on success.

4. **Update SourceDetailPage.tsx**: Same pattern for endpoint deletion. `resourceName` = endpoint URL (the unique identifier visible to the user).

---

### Backend

**Requirement**: When an endpoint is deleted, all events that have at least one `event_endpoint_deliveries` row for that endpoint must also be deleted. This must happen in a single DB transaction.

**Note**: Deleting an event already cascades to `event_endpoint_deliveries` and `delivery_attempts` (existing FK `ON DELETE CASCADE`). So deleting the events first and then the endpoint is the correct order.

**Changes required:**

1. **`EventRepositoryPort`** (`src/Application/Port/EventRepositoryPort.php`) — add method:
   ```php
   public function deleteByEndpointId(string $endpointId): void;
   ```

2. **`DoctrineEventRepository`** (`src/Infrastructure/Persistence/DoctrineEventRepository.php`) — implement `deleteByEndpointId`: query events joined through `event_endpoint_deliveries` where `endpoint_id = :id`, then bulk-delete via DQL `DELETE` or iterate + `remove()`.

3. **`DeleteEndpointUseCase`** (`src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php`) — wrap the full operation in a transaction using the existing `TransactionPort` (at `src/Infrastructure/Transaction/`):
   1. Find + validate endpoint (existing)
   2. `$this->eventRepository->deleteByEndpointId($id)` — delete related events
   3. `$this->endpointRepository->delete($id)` — delete endpoint (cascade handles deliveries)
   - Add `EventRepositoryPort` constructor injection
   - Log `INFO` for events deleted count alongside the existing endpoint deletion log

4. **Tests** — add a test in `tests/` covering `DeleteEndpointUseCase` with a mock `EventRepositoryPort` verifying `deleteByEndpointId` is called before `delete`.

---

## Files to Create / Modify

| File | Action |
|------|--------|
| `frontend/src/components/ui/dialog.tsx` | Create (via `npx shadcn add dialog`) |
| `frontend/src/components/DeleteConfirmModal.tsx` | Create |
| `frontend/src/pages/DashboardPage.tsx` | Modify — replace `window.confirm` with modal |
| `frontend/src/pages/SourceDetailPage.tsx` | Modify — replace `window.confirm` with modal |
| `src/Application/Port/EventRepositoryPort.php` | Modify — add `deleteByEndpointId` |
| `src/Infrastructure/Persistence/DoctrineEventRepository.php` | Modify — implement method |
| `src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php` | Modify — add transaction + event deletion |
| `tests/Application/UseCase/Endpoint/DeleteEndpointUseCaseTest.php` | Create or modify |

---

## Verification

1. **Frontend modal**: Open the dashboard, click delete on a source → modal appears, type wrong name → button stays disabled, type exact name → button enables, confirm → toast success + record removed from list.
2. **Frontend modal**: Open a source detail, delete an endpoint → same name-typing flow.
3. **Backend cascade**: Delete an endpoint via `DELETE /api/v1/endpoints/{id}` → verify in DB that related `events`, `event_endpoint_deliveries`, and `delivery_attempts` rows are all gone.
4. **PHPUnit**: `php bin/phpunit tests/Application/UseCase/Endpoint/DeleteEndpointUseCaseTest.php` passes.
