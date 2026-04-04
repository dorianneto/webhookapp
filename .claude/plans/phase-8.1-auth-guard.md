# Phase 8.1 — Authentication Guard

## Context
All `/api/*` routes are already protected via `access_control` in `security.yaml` (`ROLE_USER` required). But no `entry_point` is configured, so unauthenticated requests receive Symfony's default HTML redirect instead of a JSON 401. The frontend `AuthContext` already handles 401 from the initial `/api/v1/me` check, but individual page-level fetches (DashboardPage, SourceDetailPage, etc.) don't redirect on 401 — they just show an error string. Task 8.1 fixes both gaps.

---

## Backend — JSON entry point

### New: `src/Security/JsonAuthenticationEntryPoint.php`
Implements `AuthenticationEntryPointInterface`. Returns `{"error": "Unauthorized"}` with status 401.
```php
public function start(Request $request, ?AuthenticationException $authException = null): Response
{
    return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
}
```

### Modify: `config/packages/security.yaml`
Add `entry_point` to the `main` firewall:
```yaml
firewalls:
    main:
        entry_point: App\Security\JsonAuthenticationEntryPoint
        ...
```

---

## Frontend — Global 401 handler

### New: `frontend/src/lib/apiFetch.ts`
Thin wrapper around `fetch`. On 401, dispatches a `CustomEvent('auth:unauthorized')` so `AuthContext` can clear state globally.
```typescript
export async function apiFetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const res = await fetch(input, init)
  if (res.status === 401) {
    window.dispatchEvent(new CustomEvent('auth:unauthorized'))
  }
  return res
}
```

### Modify: `frontend/src/contexts/AuthContext.tsx`
In the provider `useEffect`, add a window listener for `auth:unauthorized`:
```typescript
useEffect(() => {
  const handler = () => {
    setUser(null)
    localStorage.removeItem('waas_user')
  }
  window.addEventListener('auth:unauthorized', handler)
  return () => window.removeEventListener('auth:unauthorized', handler)
}, [])
```
> When user state drops to null, `ProtectedRoute` automatically redirects to `/login`.

### Replace `fetch` → `apiFetch` in all pages
The `/api/v1/me` call in `AuthContext` stays as plain `fetch` (it's the session probe; its 401 is already handled correctly). All other API calls in pages need `apiFetch`:

| File | Fetch calls to replace |
|---|---|
| `frontend/src/pages/DashboardPage.tsx` | `/api/v1/sources` GET, DELETE |
| `frontend/src/pages/SourceDetailPage.tsx` | sources GET, endpoints GET, events GET, endpoint DELETE, events refresh |
| `frontend/src/pages/NewSourcePage.tsx` | `/api/v1/sources` POST |
| `frontend/src/pages/NewEndpointPage.tsx` | `/api/v1/sources/${sourceId}/endpoints` POST |
| `frontend/src/pages/EventDetailPage.tsx` | `/api/v1/events/${eventId}` GET |

---

## Files to create
- `src/Security/JsonAuthenticationEntryPoint.php`
- `frontend/src/lib/apiFetch.ts`

## Files to modify
- `config/packages/security.yaml` — add `entry_point`
- `frontend/src/contexts/AuthContext.tsx` — add `auth:unauthorized` listener
- `frontend/src/pages/DashboardPage.tsx` — replace `fetch` with `apiFetch`
- `frontend/src/pages/SourceDetailPage.tsx` — replace `fetch` with `apiFetch`
- `frontend/src/pages/NewSourcePage.tsx` — replace `fetch` with `apiFetch`
- `frontend/src/pages/NewEndpointPage.tsx` — replace `fetch` with `apiFetch`
- `frontend/src/pages/EventDetailPage.tsx` — replace `fetch` with `apiFetch`
- `TASKS.md` — check off 8.1

---

## Verification
```bash
php bin/phpunit
cd frontend && npx tsc --noEmit
```
Manual: log out, then try `curl -s http://localhost:8080/api/v1/sources` → expect `{"error":"Unauthorized."}` with 401.
