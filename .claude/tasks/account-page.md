# Tasks: Account Page

PRD: `.claude/prd/account-page.md`

---

## Backend

- [x] Add `findById(string $id): ?User` to `src/Application/Port/UserRepositoryPort.php`
- [x] Add `setName(?string $name): void` and `setPasswordHash(string $passwordHash): void` setters to `src/Entity/User.php`
- [x] Implement `findById` in `src/Infrastructure/Persistence/DoctrineUserRepository.php`
- [x] Fix `save()` in `src/Infrastructure/Persistence/DoctrineUserRepository.php` — upsert pattern: if entity exists, update name + passwordHash on the managed entity; otherwise persist new
- [x] Create `src/Domain/Exception/InvalidPasswordException.php`
- [x] Create `src/Application/UseCase/UpdateAccountUseCase.php` — load user by ID, verify current password via injected callable, construct updated `Domain\User`, save, return updated user
- [x] Create `src/Controller/Api/v1/AccountController.php` — `PUT /api/v1/account`, validate name, hash new password, build verifier closure, call use case, return `{ id, email, name }`
- [x] Add/update `tests/Unit/Application/UseCase/UpdateAccountUseCaseTest.php` — cover: name update only, password change success, wrong current password throws `InvalidPasswordException`

## Frontend

- [ ] Add `updateUser(updated: User): void` to `AuthContextValue` interface and `AuthProvider` in `frontend/src/contexts/AuthContext.tsx`
- [ ] Create `frontend/src/pages/AccountPage.tsx` — name field (pre-filled), email field (read-only + muted), current/new password fields, `PUT /api/v1/account` via `apiFetch`, call `updateUser` on success, toast + inline `Alert` for feedback
- [ ] Register `/account` protected route in `frontend/src/App.tsx`
- [ ] Wire "Account" `DropdownMenuItem` to `<Link to="/account">` with `asChild` in `frontend/src/components/NavUser.tsx`
