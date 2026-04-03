# Plan: Move Frontend-Symfony Integration Before Phase 1

## Context

The user wants to access the React frontend through Symfony at `http://localhost:8000/` during development — without waiting until Phase 10. Currently:
- Vite already builds to `public/build/` (Phase 0.3 is done), and a build already exists there
- But Symfony has no route to serve `public/build/index.html` — any unmatched path returns a 404
- Phase 10.1/10.2 are where this was originally planned, but it blocks early UI development

## What to Change in TASKS.md

### 1. Insert new Phase 0.9 — Frontend-Symfony Integration

Add between Phase 0 and Phase 1:

```markdown
## Phase 0.9 — Frontend-Symfony Integration

- [ ] **0.9.1** Create `src/Controller/SpaController.php`:
  - `#[Route('/{reactRouting}', requirements: ['reactRouting' => '^(?!api|in).*'], priority: -10)]`
  - Reads and returns `public/build/index.html` as `text/html`
  - `requirements` regex `^(?!api|in).*` explicitly excludes `/api/*` and `/in/*` paths so they are never matched by this controller
  - `priority: -10` ensures all future routes win over this fallback by default
- [ ] **0.9.2** Run `npm run build` inside `frontend/`, then `docker compose up` and verify
  `http://localhost:8000/` loads the React app and assets (`/build/assets/*.js`, `/build/assets/*.css`) return 200
- [ ] **0.9.3** Verify SPA routing: navigate to a nested path (e.g. `/sources/123`) and confirm
  the React shell still loads (client-side routing handled by React Router)
```

### 2. Remove 10.1 and 10.2 from Phase 10 (or mark as covered)

- **10.1** is already covered by 0.3 (Vite `build.outDir` is set) — remove it
- **10.2** (Vite manifest integration) is simplified: the catch-all controller in 0.9.1 renders the `index.html` directly, no manifest parsing needed for MVP — remove it

### 3. Update the dependency graph at the bottom

```
Phase 0 (scaffolding)
  └─ Phase 0.9 (frontend-symfony integration)
       └─ Phase 1 (DB entities + migrations)
            ...
```

## Critical File

- `/Users/dorianneto/http/webhookapp/TASKS.md` — only file to modify

## Implementation Detail for 0.9.1

```php
// src/Controller/SpaController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{reactRouting}', name: 'spa', requirements: ['reactRouting' => '^(?!api|in).*'], priority: -10)]
class SpaController
{
    public function __invoke(string $kernel_project_dir): Response
    {
        $html = file_get_contents($kernel_project_dir . '/public/build/index.html');
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}
```

- No `AbstractController` needed — plain PHP class
- `$kernel_project_dir` is auto-wired by Symfony as a service parameter
- `requirements: ['reactRouting' => '.*']` — dot-star allows slashes in path

## Verification

1. Run `npm run build` in `frontend/`
2. `docker compose up app`
3. `curl http://localhost:8000/` — should return HTML with React bundle script tags
4. Open browser → `http://localhost:8000/` → React app loads
5. Navigate to `http://localhost:8000/login` → still loads (SPA fallback works)
6. Network tab: `/build/assets/index-*.js` and `.css` return 200 (served as static files, no PHP involved)
