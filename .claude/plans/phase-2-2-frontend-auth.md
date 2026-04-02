# Plan: Tasks 2.2.1–2.2.4 — Frontend Authentication

## Context

Wire up React auth to the Symfony session-based backend built in 2.1. React Router is not yet installed. The frontend is a bare Vite + React + TypeScript scaffold. All three tasks must be completed sequentially since 2.2.3 (global auth state + guards) underpins 2.2.1 and 2.2.2.

---

## Install

```bash
cd frontend && npm install react-router-dom
```

---

## Files

| Action | Path |
|--------|------|
| Create | `frontend/src/contexts/AuthContext.tsx` |
| Create | `frontend/src/components/ProtectedRoute.tsx` |
| Create | `frontend/src/pages/LoginPage.tsx` |
| Create | `frontend/src/pages/RegisterPage.tsx` |
| Modify | `frontend/src/App.tsx` |

---

## 2.2.3 — Global auth state (`AuthContext`)

`frontend/src/contexts/AuthContext.tsx`

- `User = { id: string; email: string }`
- `useState` initialised from `localStorage` key `waas_user` (synchronous read — no loading spinner needed)
- Exported `useAuth()` hook
- `login(email, password)` → `POST /login` → stores user in state + `localStorage`
- `logout()` → `POST /logout` → clears state + `localStorage`
- On any 401 from API calls downstream, callers clear auth state via `logout()`

`frontend/src/components/ProtectedRoute.tsx`

- Reads `user` from `useAuth()`
- If `null` → `<Navigate to="/login" replace />`
- Otherwise renders `children`

---

## 2.2.1 — `/register` route

`frontend/src/pages/RegisterPage.tsx`

- Fields: email, password, confirm password
- Client-side: validate passwords match before submitting
- `POST /register` with `{email, password}`
- On success: auto-login via `auth.login(email, password)` → `navigate('/')`
- On error: show `{"error": "..."}` from response

---

## 2.2.2 — `/login` route

`frontend/src/pages/LoginPage.tsx`

- Fields: email, password
- Calls `auth.login(email, password)` → `navigate('/')`
- On error: display error message
- Link to `/register`

---

## App.tsx rewrites

Set up `BrowserRouter` + `AuthProvider` + `Routes`:

```
/login      → <LoginPage />        (public)
/register   → <RegisterPage />     (public)
/           → <ProtectedRoute>     (redirects to /login if unauth)
/*          → <ProtectedRoute>     (catch-all placeholder)
```

---

## 2.2.4 — Logout

`frontend/src/App.tsx` — `Dashboard` placeholder component

- Displays logged-in user's email
- "Sign out" button calls `logout()` from `useAuth()`
- `logout()` POSTs to `/logout`, clears state + `localStorage` → `ProtectedRoute` redirects to `/login`

---

## Verification

```bash
cd frontend && npm run build
```

Then manually:
1. Visit `/register` → create account → redirected to `/`
2. Visit `/login` → log in → redirected to `/`
3. Reload `/` while logged in → stays on `/`
4. Clear localStorage + reload → redirected to `/login`
5. While logged in, click "Sign out" → session cleared → redirected to `/login`
