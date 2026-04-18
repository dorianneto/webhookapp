# Tasks: Delete Confirmation Modal + Endpoint Cascade

PRD: `.claude/prd/delete-confirmation.md`

---

## Backend

- [x] Add `deleteByEndpointId(string $endpointId): void` to `src/Application/Port/EventRepositoryPort.php`
- [x] Implement `deleteByEndpointId` in `src/Infrastructure/Persistence/DoctrineEventRepository.php` — query events via `event_endpoint_deliveries` join and bulk-delete
- [x] Update `src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php` — inject `EventRepositoryPort` + `TransactionPort`, wrap deletion in a transaction (delete events first, then endpoint), log events deleted count
- [x] Add/update `tests/Application/UseCase/Endpoint/DeleteEndpointUseCaseTest.php` — mock `EventRepositoryPort` and assert `deleteByEndpointId` is called before `delete`

## Frontend

- [ ] Scaffold `frontend/src/components/ui/dialog.tsx` via `npx shadcn add dialog`
- [ ] Create `frontend/src/components/DeleteConfirmModal.tsx` — Dialog with resource name input, Delete button disabled until input matches exactly, resets on close
- [ ] Update `frontend/src/pages/DashboardPage.tsx` — replace `window.confirm` with `DeleteConfirmModal` for source deletion
- [ ] Update `frontend/src/pages/SourceDetailPage.tsx` — replace `window.confirm` with `DeleteConfirmModal` for endpoint deletion
