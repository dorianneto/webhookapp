# Phase 3.3 — Source Management: Frontend

## Context

Phase 3.2 (backend API) is complete: `GET/POST /api/v1/sources` and `DELETE /api/v1/sources/{id}` are working. Phase 3.3 wires the React frontend to those endpoints, replacing the placeholder `Dashboard` component with a real sources list and adding a source-creation form.

---

## Files to Modify

- `frontend/src/App.tsx` — replace inline `Dashboard` with `DashboardPage`, add `/sources/new` route

## Files to Create

- `frontend/src/pages/DashboardPage.tsx` — sources list (task 3.3.1 + delete button)
- `frontend/src/pages/NewSourcePage.tsx` — new source form (task 3.3.2)

---

## Existing Patterns to Follow

- Page structure: `frontend/src/pages/LoginPage.tsx` — `useState` per field, inline styles, `useNavigate`
- Auth header: inline `Dashboard` in `frontend/src/App.tsx` — replicate the `user.email` + Sign out header
- Routing: `frontend/src/App.tsx` — React Router v6, `ProtectedRoute` wrapper, `Navigate`
- API calls: direct `fetch()`, check `res.ok`, parse JSON error as `{ error?: string }`
- TypeScript: interfaces for API shapes, `FormEvent` for forms

---

## Implementation Plan

### Step 1 — `DashboardPage` (tasks 3.3.1 + delete button)

**File:** `frontend/src/pages/DashboardPage.tsx`

```
interface Source {
  id: string
  name: string
  inboundUuid: string
  inboundUrl: string
  createdAt: string
}
```

State:
- `sources: Source[]`
- `loading: boolean`
- `error: string | null`

On mount: `fetch('/api/v1/sources')` → populate `sources`.

Render:
- Header: user email + "Sign out" button (same pattern as current inline Dashboard)
- "New Source" button → `navigate('/sources/new')`
- Table/list of sources — each row shows: name, inbound URL (copyable text), created date, link to `/sources/{id}`, delete button
- Delete button: `window.confirm('Delete this source?')` → `DELETE /api/v1/sources/{id}` → remove from list on success

### Step 2 — `NewSourcePage` (task 3.3.2)

**File:** `frontend/src/pages/NewSourcePage.tsx`

State: `name`, `error`, `loading`

Submit handler:
- `POST /api/v1/sources` with `{ name }`
- On success: `navigate('/sources/' + data.id)`
- On error: show `data.error`

Render: simple form with name field + submit button + "Back" link to `/`

### Step 3 — Update `App.tsx`

- Import `DashboardPage` and `NewSourcePage`
- Remove inline `Dashboard` component
- Replace `/` route's element with `<DashboardPage />`
- Add `/sources/new` route (protected) with `<NewSourcePage />`
- Add `/sources/:id` catch-handled by the existing `*` → `/` fallback (placeholder until Phase 4)

---

## Execution Order (sequential, check off as done)

- [x] 3.3.1 — Create `DashboardPage` with sources list
- [x] Delete button — add confirmation + delete call inside `DashboardPage`
- [x] 3.3.2 — Create `NewSourcePage` with creation form
- [x] Update `App.tsx` — wire up new pages and routes

---

## Verification

1. `npm run build` in `frontend/` must complete with no TypeScript errors
2. Manual smoke test (requires running stack):
   - `/` shows sources list with "New Source" button
   - "New Source" navigates to `/sources/new`; submitting form creates source and redirects back to `/sources/{id}`
   - Delete button shows confirmation; confirmed delete removes source from list
