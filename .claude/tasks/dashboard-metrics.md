# Dashboard Metrics — Implementation Tasks

All tasks follow the architecture and constraints defined in `.claude/prd/dashboard-metrics.md` and `CLAUDE.md`.

---

## Phase 1 — Application Layer

- [x] **1.1** `src/Application/Value/DashboardStats.php` — `final readonly` class; constructor params: `totalSources`, `totalEndpoints`, `totalEventsReceived`, `deliveredEventsCount`, `pendingEventsCount`, `failedEventsCount` (all `int`), `lastEventReceivedAt` (`?\DateTimeImmutable`), `quotaUsed`, `quotaLimit` (both `int`); no framework imports
- [x] **1.2** `src/Application/Port/DashboardStatsRepositoryPort.php` — single method: `getForUser(string $userId): DashboardStats`

---

## Phase 2 — Infrastructure: Persistence

- [x] **2.1** `src/Infrastructure/Persistence/DoctrineDashboardStatsRepository.php` — implements `DashboardStatsRepositoryPort`; constructor injects `Connection`, `RequestUsageRepositoryPort`, `PlanRepositoryPort`; computes all counts and last timestamp in one SQL query:
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
  After the query: call `$this->requestUsageRepository->sumRolling30Days($userId)` and `$this->planRepository->findByUserId($userId)?->getMonthlyRequestLimit() ?? 0`; cast `last_event_received_at` to `\DateTimeImmutable` or `null`; assemble and return `DashboardStats`

---

## Phase 3 — Use Case

- [x] **3.1** `src/Application/UseCase/Dashboard/GetDashboardStatsUseCase.php` — `#[WithMonologChannel('hookyard')]`; constructor: `DashboardStatsRepositoryPort`, `LoggerInterface`; method `execute(string $requestId, string $userId): DashboardStats`; log INFO on entry (`request_id`) and on success (`request_id`, `total_sources`, `total_endpoints`, `total_events`, `failed_events`)

---

## Phase 4 — Controller

- [x] **4.1** `src/Controller/Api/v1/Dashboard/DashboardController.php` — `#[Route('/dashboard', name: 'dashboard_stats', methods: ['GET'])]`; `#[WithMonologChannel('hookyard')]`; constructor: `GetDashboardStatsUseCase`, `Security`, `LoggerInterface`; reads `request_id` from `$request->attributes->get('request_id')`; returns HTTP 401 if user is not `App\Entity\User`; calls use case; serializes `DashboardStats` to JSON response with all nine fields; logs INFO on request received and response dispatched; no changes to `config/routes.yaml` needed

---

## Phase 5 — Tests

- [x] **5.1** `tests/Unit/Application/UseCase/Dashboard/GetDashboardStatsUseCaseTest.php` — three test cases:
  - `testExecuteReturnsDashboardStats` — mock repository returns a populated `DashboardStats`; assert `getForUser` called with correct `userId`; assert returned object matches
  - `testExecuteWithNullLastEventReceivedAt` — mock returns `DashboardStats` with `lastEventReceivedAt = null`; assert no exception and `lastEventReceivedAt === null`
  - `testExecuteWithZeroQuotaLimit` — mock returns `DashboardStats` with `quotaLimit = 0`; assert `quotaLimit === 0`
  - Use `createMock(DashboardStatsRepositoryPort::class)` and `new NullLogger()`
- [x] **5.2** Run `php bin/phpunit tests/Unit/Application/UseCase/Dashboard/` — all tests pass (3/3, 7 assertions)

---

## Phase 6 — Frontend: Routing & Sources Page

- [x] **6.1** Rename `frontend/src/pages/DashboardPage.tsx` → `frontend/src/pages/SourcesPage.tsx`; rename exported function from `DashboardPage` to `SourcesPage`
- [x] **6.2** `frontend/src/App.tsx` — add `import SourcesPage from "./pages/SourcesPage"`; add `/sources` route (wrapped in `ProtectedRoute` + `Layout`) before the `/sources/:sourceId` route; keep `/` pointing to the new `DashboardPage`
- [x] **6.3** `frontend/src/pages/SourceDetailPage.tsx` — update breadcrumb link from `<Link to="/">Sources</Link>` → `<Link to="/sources">Sources</Link>`

---

## Phase 7 — Frontend: Dashboard Page

- [x] **7.1** `frontend/src/pages/DashboardPage.tsx` — new file; `useState` for `stats` (`DashboardStats | null`), `loading`, `error`; `useEffect` calls `apiFetch('/api/v1/dashboard')` once on mount; inline `DashboardStats` TypeScript interface; wrap page in `<Layout>`
- [x] **7.2** Loading state: render 8 `<Card>` skeletons using `<Skeleton className="h-20 w-full" />` inside `<CardContent>` for each slot
- [x] **7.3** Error state: `<Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>`
- [x] **7.4** Stats grid: `<div className="grid grid-cols-2 gap-4 md:grid-cols-4">` — 7 plain stat cards + 1 quota card; each card uses `<Card>` + `<CardHeader>` + `<CardContent>` with `<CardTitle className="text-sm font-medium text-muted-foreground">` for the label and `<p className="text-3xl font-semibold">` for the value; cards in order: Total Sources, Total Endpoints, Total Events, Last Event Received (`toLocaleString()` or `"Never"` when null), Delivered, Pending, Failed
- [x] **7.5** Quota card: visible only when `stats.quotaLimit > 0`; `<CardDescription>` shows `{quotaUsed.toLocaleString()} / {quotaLimit.toLocaleString()}`; fill bar uses `style={{ width: \`${Math.min(100, Math.round((quotaUsed / quotaLimit) * 100))}%\` }}` on `<div className="h-full bg-primary transition-all">` inside `<div className="h-2 rounded-full bg-muted overflow-hidden">`

---

## Phase 8 — Frontend: Sidebar Navigation

- [x] **8.1** `frontend/src/components/AppSidebar.tsx` — replace placeholder `navMain` array with `[{ title: "Dashboard", url: "/", icon: IconDashboard }, { title: "Sources", url: "/sources", icon: IconDatabase }]`; remove unused placeholder items
- [x] **8.2** `frontend/src/components/NavMain.tsx` — add `import { Link } from "react-router-dom"`; change `<SidebarMenuButton>` inside the items loop to use `asChild` with `<Link to={item.url}>` so nav items actually navigate

---

## Dependency Order

```
Phase 1 (value object + port)
  └─ Phase 2 (repository)
       └─ Phase 3 (use case)
            └─ Phase 4 (controller)
                 └─ Phase 5 (tests)

Phase 6 (routing + sources page rename)
  └─ Phase 7 (dashboard page)
       └─ Phase 8 (sidebar nav)
```
