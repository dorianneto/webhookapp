# Product Requirements Document — Account Page

**Stack:** Symfony 7 (API) + React (Frontend) — Monolith

---

## 1. Problem statement

Authenticated users have no way to update their profile details. The NavUser dropdown already includes an inert "Account" item that goes nowhere. This PRD specifies the implementation to wire that item to a functional account settings page where users can edit their name and password.

---

## 2. Goals

- A user can navigate to an Account page via the NavUser dropdown.
- The page displays the user's current email (read-only) and name (editable).
- The user can change their name.
- The user can change their password by providing their current password and a new password.
- Changes are persisted via a new `PUT /api/v1/account` endpoint.
- After a successful save, the sidebar immediately reflects any name change.

---

## 3. Non-goals (explicitly out of scope)

- Changing email address
- Email verification flow
- Account deletion
- Two-factor authentication
- Profile picture / avatar

---

## 4. API

### `PUT /api/v1/account`

Requires an active session (`ROLE_USER`). Covered by the existing `/api/**` access control rule — no new security config needed.

**Request body (JSON):**

```json
{
  "name": "Alice Smith",
  "currentPassword": "oldSecret",
  "newPassword": "newSecret"
}
```

| Field | Required | Notes |
|---|---|---|
| `name` | Yes | Not blank |
| `currentPassword` | Conditional | Required when `newPassword` is present |
| `newPassword` | No | If absent, password is not changed |

**Success — HTTP 200:**

```json
{ "id": "...", "email": "alice@example.com", "name": "Alice Smith" }
```

**Error responses:**

| Condition | Status | Body |
|---|---|---|
| Invalid JSON | 422 | `{"error": "Invalid JSON."}` |
| Blank name | 422 | `{"error": "Name is required."}` |
| `newPassword` provided but `currentPassword` absent | 422 | `{"error": "Current password is required when setting a new password."}` |
| Wrong current password | 422 | `{"error": "Current password is incorrect."}` |
| Not authenticated | 401 | `{"error": "Unauthorized."}` |

---

## 5. Backend implementation

### 5.1 `UserRepositoryPort` — add `findById`

**File:** `src/Application/Port/UserRepositoryPort.php`

Add:

```php
public function findById(string $id): ?User;
```

Required because the session-deserialized `User` entity has an empty `passwordHash` (see `__unserialize`). The use case must reload the user from the DB to validate `currentPassword` against the real stored hash.

### 5.2 `Entity/User` — add setters

**File:** `src/Entity/User.php`

Add two public setters:

```php
public function setName(?string $name): void { $this->name = $name; }
public function setPasswordHash(string $passwordHash): void { $this->passwordHash = $passwordHash; }
```

Required by the upsert path in `DoctrineUserRepository::save()` (Doctrine ORM 3.x removed `merge()`).

### 5.3 `DoctrineUserRepository` — implement `findById` and fix `save()`

**File:** `src/Infrastructure/Persistence/DoctrineUserRepository.php`

Implement `findById`:

```php
public function findById(string $id): ?DomainUser
{
    $entity = $this->entityManager->getRepository(UserEntity::class)->find($id);
    return $entity?->toDomain();
}
```

Fix `save()` to handle both INSERT and UPDATE:

```php
public function save(DomainUser $user): void
{
    $existing = $this->entityManager->getRepository(UserEntity::class)->find($user->getId());
    if ($existing instanceof UserEntity) {
        $existing->setName($user->getName());
        $existing->setPasswordHash($user->getPasswordHash());
    } else {
        $entity = UserEntity::fromDomain($user);
        $this->entityManager->persist($entity);
    }
    $this->entityManager->flush();
}
```

The INSERT path (registration) is unchanged — `find()` returns null for a new UUID, so `persist()` is called as before.

### 5.4 `InvalidPasswordException`

**File:** `src/Domain/Exception/InvalidPasswordException.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\Exception;
final class InvalidPasswordException extends \DomainException {}
```

### 5.5 `UpdateAccountUseCase`

**File:** `src/Application/UseCase/UpdateAccountUseCase.php`

- Constructor: `UserRepositoryPort`, `LoggerInterface`
- Single `execute()` method:

```
execute(
    string $requestId,
    string $userId,
    string $name,
    ?string $newPasswordHash,
    ?string $currentPasswordPlain,
    callable $passwordVerifier,
): Domain\User
```

Logic:
1. Load user via `findById($userId)`.
2. If `$newPasswordHash !== null`, call `$passwordVerifier($currentPasswordPlain, $loadedUser->getPasswordHash())`. Throw `InvalidPasswordException` on false.
3. Construct an updated `Domain\User` value object (new name; new hash or original hash if unchanged).
4. Call `$this->userRepository->save($updated)`.
5. Return the updated user.

The `$passwordVerifier` callable is injected by the controller so that `UserPasswordHasherInterface` (an infrastructure concern) never leaks into the Application layer.

### 5.6 `AccountController`

**File:** `src/Controller/Api/v1/AccountController.php`

- Route: `#[Route('/api/v1/account', name: 'account_update', methods: ['PUT'])]`
- `#[WithMonologChannel('hookyard')]`
- Constructor injection: `UpdateAccountUseCase`, `UserPasswordHasherInterface`, `Security`, `ValidatorInterface`, `LoggerInterface`

Flow:
1. Read `request_id` from request attributes; log request received.
2. Resolve current user via `$this->security->getUser()`; guard `instanceof UserEntity`; return 401 if not.
3. Parse JSON body; return 422 on invalid JSON.
4. Extract `name`, `currentPassword`, `newPassword`.
5. Validate `name` not blank via Symfony Validator; return 422 with violation message.
6. If `newPassword` is present but `currentPassword` is absent or empty, return 422.
7. Hash `newPassword` via `$this->passwordHasher->hashPassword($user, $newPassword)` if provided; otherwise `null`.
8. Build `$passwordVerifier` closure using `$this->passwordHasher->isPasswordValid()`.
9. Call `$useCase->execute()`; catch `InvalidPasswordException` → return 422.
10. Log response dispatched; return 200 with `{ id, email, name }`.

---

## 6. Frontend implementation

### 6.1 `AuthContext` — expose `updateUser`

**File:** `frontend/src/contexts/AuthContext.tsx`

Add to `AuthContextValue` interface:

```typescript
updateUser: (updated: User) => void
```

Implement inside `AuthProvider`:

```typescript
const updateUser = (updated: User): void => {
  setUser(updated)
  localStorage.setItem('waas_user', JSON.stringify(updated))
}
```

Include in the context `value` prop.

### 6.2 `AccountPage`

**File:** `frontend/src/pages/AccountPage.tsx`

- Pre-fill `name` from `useAuth().user?.name ?? ''`.
- Email `<Input>` is `readOnly` with muted styling (`text-muted-foreground bg-muted cursor-not-allowed`).
- `currentPassword` and `newPassword` fields are empty by default. Both are sent only when `newPassword` is non-empty.
- On successful submit: call `updateUser(responseData)`, clear password fields, show `toast.success('Account updated.')`. Stay on the page (settings UX — no redirect).
- On error: display an `Alert` with the error message.
- Breadcrumb: Dashboard → Account.
- shadcn components: `Card`, `CardHeader`, `CardTitle`, `CardContent`, `Label`, `Input`, `Button`, `Alert`, `Breadcrumb`.

### 6.3 `App.tsx` — register route

**File:** `frontend/src/App.tsx`

Add before the `*` catch-all route:

```tsx
<Route
  path="/account"
  element={
    <ProtectedRoute>
      <Layout>
        <AccountPage />
      </Layout>
    </ProtectedRoute>
  }
/>
```

### 6.4 `NavUser.tsx` — wire the Account item

**File:** `frontend/src/components/NavUser.tsx`

Add `import { Link } from 'react-router-dom'` and change the inert `DropdownMenuItem`:

```tsx
<DropdownMenuItem asChild>
  <Link to="/account">
    <IconUserCircle />
    Account
  </Link>
</DropdownMenuItem>
```

`asChild` (standard Radix UI pattern) renders `Link` as the item root for proper React Router navigation.

---

## 7. Files summary

| Action | File |
|---|---|
| Create | `src/Domain/Exception/InvalidPasswordException.php` |
| Create | `src/Application/UseCase/UpdateAccountUseCase.php` |
| Create | `src/Controller/Api/v1/AccountController.php` |
| Create | `frontend/src/pages/AccountPage.tsx` |
| Modify | `src/Application/Port/UserRepositoryPort.php` — add `findById` |
| Modify | `src/Entity/User.php` — add `setName`, `setPasswordHash` |
| Modify | `src/Infrastructure/Persistence/DoctrineUserRepository.php` — implement `findById`, fix `save()` |
| Modify | `frontend/src/contexts/AuthContext.tsx` — add `updateUser` |
| Modify | `frontend/src/App.tsx` — add `/account` route |
| Modify | `frontend/src/components/NavUser.tsx` — wire Account link |

---

## 8. Verification

1. Run `php bin/phpunit` — all existing tests pass.
2. Log in → open NavUser dropdown → click "Account" → lands on `/account`.
3. Edit name → Save → sidebar reflects new name immediately, toast appears.
4. Provide wrong current password → inline error displayed, no change persisted.
5. Provide correct current password + new password → save succeeds; re-login with new password works.
6. Email field cannot be typed into; `PUT` body with an `email` field is ignored server-side.
