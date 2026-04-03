# Phase 4.3 — Endpoint Frontend

## Context
Endpoints are managed within the context of a source. The dashboard already links source names to `/sources/{id}` (see `DashboardPage.tsx:82`) — but that route doesn't exist yet. This phase creates the detail page and the new endpoint form page.

## Files to Create

### 1. `frontend/src/pages/SourceDetailPage.tsx`
Route: `/sources/:sourceId`

**Layout:**
- Header: source name + back link to `/`
- "Endpoints" section:
  - "Add Endpoint" button → navigates to `/sources/:sourceId/endpoints/new`
  - Table: URL | Created | (Delete button)
  - Delete calls `DELETE /api/v1/endpoints/{id}`, removes row on success
  - Empty state: "No endpoints yet."
- "Events" section: placeholder `<p>Events coming in Phase 7.</p>` (per task scope)

**Data fetching:**
- On mount, two parallel fetches:
  1. `fetch('/api/v1/sources')` → find source by `sourceId` param for the header name (no single-source GET endpoint exists; fetching all is fine given low count)
  2. `fetch('/api/v1/sources/{sourceId}/endpoints')` → populate endpoint list
- Use `Promise.all([...])` for both fetches
- `useState` + `useEffect` pattern matching `DashboardPage.tsx`

**Types:**
```ts
interface Endpoint {
  id: string
  sourceId: string
  url: string
  createdAt: string
}
```

### 2. `frontend/src/pages/NewEndpointPage.tsx`
Route: `/sources/:sourceId/endpoints/new`

**Layout:**
- Header: "Add Endpoint" + back link to `/sources/:sourceId`
- Form: single `url` text input + Submit button
- On submit: `POST /api/v1/sources/{sourceId}/endpoints` with `{url}`
- On success (201): navigate to `/sources/${sourceId}`
- On error: show error message from response or generic fallback

**Pattern:** mirror `NewSourcePage.tsx` — controlled input, `useState` for error/loading, `useNavigate` for redirect.

## Files to Modify

### `frontend/src/App.tsx`
Add two new protected routes (insert before the catch-all `*` route):
```tsx
import SourceDetailPage from './pages/SourceDetailPage'
import NewEndpointPage from './pages/NewEndpointPage'

// Inside <Routes>:
<Route path="/sources/:sourceId" element={<ProtectedRoute><SourceDetailPage /></ProtectedRoute>} />
<Route path="/sources/:sourceId/endpoints/new" element={<ProtectedRoute><NewEndpointPage /></ProtectedRoute>} />
```
> Insert these BEFORE the `<Route path="/sources/new" ...>` so that React Router doesn't greedily match `/sources/new` as a `:sourceId`.
> Actually, React Router v6 is not greedy — it uses specificity, so `/sources/new` (literal) will match before `/sources/:sourceId` (param). Order is fine either way, but keep `/sources/new` before `/sources/:sourceId` to be explicit.

## Verification
```bash
npm run dev   # in frontend/ or via docker compose
```
1. Navigate to dashboard → click a source name → should load `/sources/{id}` with empty endpoint list
2. Click "Add Endpoint" → fill URL → submit → redirects back, endpoint appears in list
3. Click delete on an endpoint → endpoint disappears from list
4. Invalid URL → backend returns 422 → error displayed in form
