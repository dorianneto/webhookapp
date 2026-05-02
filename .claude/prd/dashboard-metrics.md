# PRD: Dashboard Metrics Page

## Problem

The current home page (`/`) shows a flat list of sources — it provides no at-a-glance health signal for a user's account. Customers have no quick way to know how many events failed, whether their pipeline is active, or whether they are approaching a quota limit. A metrics dashboard makes the platform's value visible immediately on login.

## Goal

Replace the current source-list home page with a proper metrics dashboard showing per-user KPIs in metric cards. Move the sources list to `/sources`. Introduce a single backend endpoint that computes all stats in one request.

---

## Requirements

### Functional

- **FR-1**: A new `GET /api/v1/dashboard` endpoint returns per-user aggregate stats.
- **FR-2**: The response includes: total sources, total endpoints, total events received, delivered events, pending events, failed events, last event received timestamp, and quota usage (used + limit).
- **FR-3**: `lastEventReceivedAt` is `null` when the user has received no events.
- **FR-4**: `quotaLimit` is `0` when no plan is assigned to the user. The frontend hides the quota card in this case.
- **FR-5**: The `/` route renders the metrics dashboard with one card per stat.
- **FR-6**: The sources list moves to a new `/sources` route.
- **FR-7**: Sidebar navigation links are updated to point to real routes: Dashboard (`/`) and Sources (`/sources`).
- **FR-8**: Breadcrumbs in `SourceDetailPage` that previously linked to `/` are updated to link to `/sources`.
- **FR-9**: All stats are scoped to the authenticated user — no cross-user data exposure.

### Non-functional

- **NFR-1**: All stats are fetched in a single API call (`GET /api/v1/dashboard`).
- **NFR-2**: The backend computes source/endpoint counts, event status breakdown, and last event timestamp in one SQL query using `FILTER` conditional aggregates (PostgreSQL 17).
- **NFR-3**: Quota usage reuses existing `DoctrineRequestUsageRepository::sumRolling30Days` and `DoctrinePlanRepository::findByUserId` — no duplicated SQL.
- **NFR-4**: Architecture constraints preserved: Domain layer zero Symfony/Doctrine imports; Application layer defines ports only; Infrastructure implements them.
- **NFR-5**: No schema changes required — all metrics are derived from existing tables.

---

## API

### `GET /api/v1/dashboard`

Authenticated. Returns HTTP 200.

```json
{
  "totalSources": 3,
  "totalEndpoints": 7,
  "totalEventsReceived": 1024,
  "deliveredEventsCount": 990,
  "pendingEventsCount": 10,
  "failedEventsCount": 24,
  "lastEventReceivedAt": "2026-05-02T14:35:22+00:00",
  "quotaUsed": 312,
  "quotaLimit": 10000
}
```

`lastEventReceivedAt` is JSON `null` when the user has no events. `quotaLimit` of `0` means no plan is assigned.

---

## Implementation

### Backend

#### Value Object

**Create** `src/Application/Value/DashboardStats.php`

```php
final readonly class DashboardStats
{
    public function __construct(
        public int $totalSources,
        public int $totalEndpoints,
        public int $totalEventsReceived,
        public int $deliveredEventsCount,
        public int $pendingEventsCount,
        public int $failedEventsCount,
        public ?\DateTimeImmutable $lastEventReceivedAt,
        public int $quotaUsed,
        public int $quotaLimit,
    ) {}
}
```

#### Port

**Create** `src/Application/Port/DashboardStatsRepositoryPort.php`

```php
interface DashboardStatsRepositoryPort
{
    public function getForUser(string $userId): DashboardStats;
}
```

#### Repository

**Create** `src/Infrastructure/Persistence/DoctrineDashboardStatsRepository.php`

Inject `Connection`, `RequestUsageRepositoryPort`, and `PlanRepositoryPort`. Compute all stats with one SQL query plus two delegated calls to existing repositories:

**Main query** — single round-trip for counts, aggregates, and max timestamp:

```sql
SELECT
    COUNT(DISTINCT s.id)                                         AS total_sources,
    COUNT(DISTINCT ep.id)                                        AS total_endpoints,
    COUNT(DISTINCT e.id)                                         AS total_events,
    COUNT(DISTINCT e.id) FILTER (WHERE e.status = 'delivered')  AS delivered_events,
    COUNT(DISTINCT e.id) FILTER (WHERE e.status = 'pending')    AS pending_events,
    COUNT(DISTINCT e.id) FILTER (WHERE e.status = 'failed')     AS failed_events,
    MAX(e.received_at)                                           AS last_event_received_at
FROM sources s
LEFT JOIN endpoints ep ON ep.source_id = s.id
LEFT JOIN events e     ON e.source_id  = s.id
WHERE s.user_id = :userId
```

`LEFT JOIN` from `sources` to both `endpoints` and `events` independently avoids Cartesian product. `COUNT(DISTINCT ...)` guards against inflated counts from the two-branch join. `FILTER` is PostgreSQL 9.4+ and supported on PostgreSQL 17.

Then:
- `$quotaUsed  = $this->requestUsageRepository->sumRolling30Days($userId);`
- `$plan       = $this->planRepository->findByUserId($userId);`
- `$quotaLimit = $plan?->getMonthlyRequestLimit() ?? 0;`

Assemble and return a `DashboardStats` instance.

#### Use Case

**Create** `src/Application/UseCase/Dashboard/GetDashboardStatsUseCase.php`

- `#[WithMonologChannel('hookyard')]`
- Constructor: `DashboardStatsRepositoryPort`, `LoggerInterface`
- `execute(string $requestId, string $userId): DashboardStats`
- Log `INFO` on entry (`request_id`) and on success (`request_id`, `total_sources`, `total_endpoints`, `total_events`, `failed_events`)

#### Controller

**Create** `src/Controller/Api/v1/Dashboard/DashboardController.php`

- `#[Route('/dashboard', name: 'dashboard_stats', methods: ['GET'])]`
- `#[WithMonologChannel('hookyard')]`
- Constructor: `GetDashboardStatsUseCase`, `Security`, `LoggerInterface`
- Read `request_id` from `$request->attributes->get('request_id')`
- Return HTTP 401 if user is not authenticated
- Log `INFO` on request received and response dispatched
- Serialize `DashboardStats` to the JSON shape defined in the API section above
- No changes to `config/routes.yaml` — the controller directory is already scanned by the `api_v1` resource

#### Unit Tests

**Create** `tests/Unit/Application/UseCase/Dashboard/GetDashboardStatsUseCaseTest.php`

| Test | What it verifies |
|---|---|
| `testExecuteReturnsDashboardStats` | Repository called with correct `userId`; returned `DashboardStats` propagated unchanged |
| `testExecuteWithNullLastEventReceivedAt` | `null` timestamp does not throw; returned stats have `lastEventReceivedAt === null` |
| `testExecuteWithZeroQuotaLimit` | `quotaLimit === 0` returned when plan is absent |

Use `createMock(DashboardStatsRepositoryPort::class)` and `new NullLogger()`.

---

### Frontend

#### TypeScript interface (inline in `DashboardPage.tsx`)

```typescript
interface DashboardStats {
  totalSources: number
  totalEndpoints: number
  totalEventsReceived: number
  deliveredEventsCount: number
  pendingEventsCount: number
  failedEventsCount: number
  lastEventReceivedAt: string | null
  quotaUsed: number
  quotaLimit: number
}
```

#### New `DashboardPage.tsx`

**Create** `frontend/src/pages/DashboardPage.tsx`

- `useState` for `stats`, `loading`, `error`
- `useEffect` calls `apiFetch('/api/v1/dashboard')` once on mount
- Loading state: render skeleton cards using `<Skeleton className="h-20 w-full" />` inside `<CardContent>` for each card slot
- Error state: `<Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>`
- Stats grid: `<div className="grid grid-cols-2 gap-4 md:grid-cols-4">`
- Each stat card uses `<Card>` + `<CardHeader>` + `<CardContent>` with `<CardTitle className="text-sm font-medium text-muted-foreground">` for the label and `<p className="text-3xl font-semibold">` for the value

**Eight cards in order:**

| # | Card title | Value |
|---|---|---|
| 1 | Total Sources | `stats.totalSources` |
| 2 | Total Endpoints | `stats.totalEndpoints` |
| 3 | Total Events | `stats.totalEventsReceived` |
| 4 | Last Event Received | `new Date(stats.lastEventReceivedAt).toLocaleString()` or `"Never"` |
| 5 | Delivered | `stats.deliveredEventsCount` |
| 6 | Pending | `stats.pendingEventsCount` |
| 7 | Failed | `stats.failedEventsCount` |
| 8 | Request Quota (30d) | progress bar (hidden when `quotaLimit === 0`) |

**Quota card** uses a fill bar to show usage percentage. The fill width is a computed inline style (`style={{ width: \`${pct}%\` }}`) — this is the one acceptable use of `style={}` because a dynamic percentage cannot be a static Tailwind class. All other styling uses utility classes only.

```tsx
<div className="h-2 rounded-full bg-muted overflow-hidden">
  <div
    className="h-full bg-primary transition-all"
    style={{ width: `${Math.min(100, Math.round((stats.quotaUsed / stats.quotaLimit) * 100))}%` }}
  />
</div>
```

Include `<CardDescription>` with `{stats.quotaUsed.toLocaleString()} / {stats.quotaLimit.toLocaleString()}` above the bar.

Wrap the whole page in `<Layout>`.

#### Rename and update `SourcesPage.tsx`

**Rename** `frontend/src/pages/DashboardPage.tsx` → `frontend/src/pages/SourcesPage.tsx`

- Rename the exported function from `DashboardPage` to `SourcesPage`
- No other changes needed

#### `App.tsx` routing

**Modify** `frontend/src/App.tsx`

1. Add `import SourcesPage from "./pages/SourcesPage"`
2. Add `/sources` route (wrapped in `ProtectedRoute` + `Layout`) before the `/sources/:sourceId` route
3. The existing `/` route keeps `DashboardPage` (now pointing to the new metrics page)
4. All sub-routes under `/sources/:sourceId` are unchanged

#### Sidebar navigation

**Modify** `frontend/src/components/AppSidebar.tsx`

Replace the placeholder `navMain` array with:

```typescript
const navMain = [
  { title: "Dashboard", url: "/",        icon: IconDashboard },
  { title: "Sources",   url: "/sources", icon: IconDatabase  },
]
```

Remove unused placeholder items (`Lifecycle`, `Analytics`, `Projects`, `Team`).

**Modify** `frontend/src/components/NavMain.tsx`

Add `import { Link } from "react-router-dom"`.

Change `<SidebarMenuButton>` inside the items loop to:

```tsx
<SidebarMenuButton tooltip={item.title} asChild>
  <Link to={item.url}>
    {item.icon && <item.icon />}
    <span>{item.title}</span>
  </Link>
</SidebarMenuButton>
```

#### Breadcrumb fix

**Modify** `frontend/src/pages/SourceDetailPage.tsx`

Change `<Link to="/">Sources</Link>` → `<Link to="/sources">Sources</Link>` in the breadcrumb.

---

## Files Summary

| Action | File |
|---|---|
| Create | `src/Application/Value/DashboardStats.php` |
| Create | `src/Application/Port/DashboardStatsRepositoryPort.php` |
| Create | `src/Application/UseCase/Dashboard/GetDashboardStatsUseCase.php` |
| Create | `src/Infrastructure/Persistence/DoctrineDashboardStatsRepository.php` |
| Create | `src/Controller/Api/v1/Dashboard/DashboardController.php` |
| Create | `tests/Unit/Application/UseCase/Dashboard/GetDashboardStatsUseCaseTest.php` |
| Create | `frontend/src/pages/DashboardPage.tsx` (new metrics page) |
| Rename | `frontend/src/pages/DashboardPage.tsx` → `frontend/src/pages/SourcesPage.tsx` |
| Modify | `frontend/src/App.tsx` |
| Modify | `frontend/src/components/AppSidebar.tsx` |
| Modify | `frontend/src/components/NavMain.tsx` |
| Modify | `frontend/src/pages/SourceDetailPage.tsx` |

---

## Verification

| # | Check |
|---|---|
| 1 | `GET /api/v1/dashboard` returns HTTP 200 with all nine fields for an authenticated user |
| 2 | `GET /api/v1/dashboard` returns HTTP 401 for an unauthenticated request |
| 3 | `lastEventReceivedAt` is `null` in JSON for a user with no events |
| 4 | `quotaUsed` reflects only the rolling 30-day window |
| 5 | `quotaLimit` is `0` when no plan is assigned |
| 6 | All stats are scoped to the authenticated user — a second user sees their own data |
| 7 | `php bin/phpunit tests/Unit/Application/UseCase/Dashboard/` passes |
| 8 | `php bin/console debug:router \| grep dashboard` shows `GET /api/v1/dashboard` |
| 9 | `/` renders the metrics dashboard with 7 stat cards (quota card hidden when no plan) |
| 10 | `/sources` renders the sources table (same content as `/` previously) |
| 11 | Sidebar "Dashboard" link navigates to `/` |
| 12 | Sidebar "Sources" link navigates to `/sources` |
| 13 | Breadcrumb on SourceDetailPage links to `/sources` |
| 14 | Skeleton cards appear during loading |
| 15 | Error alert appears when the API call fails |
| 16 | Quota card is hidden when `quotaLimit === 0` |
| 17 | "Never" is displayed when `lastEventReceivedAt` is null |
| 18 | `npm run build` succeeds with no TypeScript errors |
| 19 | All existing routes still work: `/sources/:sourceId`, `/sources/new`, `/sources/:sourceId/endpoints/new`, `/sources/:sourceId/events/:eventId`, `/account` |
