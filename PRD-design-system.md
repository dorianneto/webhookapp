# PRD — Design System with shadcn/ui

## Context

The current frontend (React 18 + TypeScript + Vite) has no cohesive design system. Styling is split between plain CSS files with custom variables and heavy inline `style={}` objects on individual page components. This creates inconsistency and slows down UI work because every new page re-invents layout patterns.

This PRD establishes shadcn/ui + Tailwind CSS as the standard UI layer. Once installed, all future frontend work should use these primitives. All existing pages will be migrated in the same effort to create a clean break from the old approach.

---

## Goals

1. Install and configure Tailwind CSS v4 + shadcn/ui in the existing Vite project.
2. Define the project's design tokens (colors, typography, radius) via shadcn's CSS variable system.
3. Install the full initial component set covering every current use case.
4. Migrate all existing pages to use Tailwind utilities and shadcn components.
5. Retain both light and dark theme support (the current project already responds to `prefers-color-scheme`).

## Non-goals

- No new pages or features — design system only.
- No changes to the Symfony backend.
- No custom component authoring beyond what shadcn provides.
- No Storybook or component docs site.

---

## Technical Setup

### Stack versions (current)
- Vite **8.0.1**
- React **18.3.1** + TypeScript **5.9.3**
- React Router **7.13.2**
- Build output: `../public/build` (served by Symfony)

### Installation steps

#### 1. Tailwind CSS v4 (Vite plugin)

Tailwind v4 ships a first-class Vite plugin — no `tailwind.config.js` needed.

```bash
cd frontend
npm install tailwindcss @tailwindcss/vite
```

Update `vite.config.ts`:
```ts
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  // existing build/base config unchanged
})
```

Replace `index.css` content with a single import:
```css
@import "tailwindcss";
```

#### 2. Path alias (@/)

shadcn CLI and components expect `@/` to resolve to `src/`.

`vite.config.ts`:
```ts
resolve: {
  alias: { '@': path.resolve(__dirname, './src') }
}
```

`tsconfig.app.json`:
```json
"paths": { "@/*": ["./src/*"] }
```

Also install `@types/node` so `path` is available in the Vite config:
```bash
npm install -D @types/node
```

#### 3. shadcn/ui init

```bash
npx shadcn@latest init
```

CLI prompts:
- Style: **Default**
- Base color: **Slate**
- CSS variables: **yes**

This generates:
- `src/lib/utils.ts` — `cn()` helper (clsx + tailwind-merge)
- `components.json` — shadcn config
- Updates `index.css` with CSS variable blocks for light + dark theme

#### 4. Install components

```bash
npx shadcn@latest add button input label form
npx shadcn@latest add table badge
npx shadcn@latest add card separator breadcrumb navigation-menu
npx shadcn@latest add alert sonner
```

All components land in `src/components/ui/`.

---

## Design Tokens

shadcn uses CSS variables mapped to Tailwind utilities. Keep the generated defaults from the `slate` base — they cover neutral grays suitable for a developer-tool product. Customize only:

| Token | Intent |
|---|---|
| `--primary` | Brand accent (current project uses `#6366f1` indigo — keep) |
| `--destructive` | Delete / error actions |
| `--radius` | Set to `0.5rem` (subtle rounding) |

Dark mode is handled automatically via shadcn's `.dark` class strategy; the existing `prefers-color-scheme` media query in `index.css` will be removed in favour of shadcn's variable blocks.

---

## Component Inventory

| shadcn component | Used in |
|---|---|
| `Button` | Every page (submit, delete, navigate) |
| `Input` + `Label` + `Form` | LoginPage, RegisterPage, NewSourcePage, NewEndpointPage |
| `Table` + `TableHeader/Body/Row/Cell` | DashboardPage, SourceDetailPage, EventDetailPage |
| `Badge` | Event status (`pending` / `delivered` / `failed`) |
| `Card` + `CardHeader/Content` | Page section containers |
| `Separator` | Visual dividers within detail pages |
| `Breadcrumb` | SourceDetailPage, EventDetailPage (navigation context) |
| `NavigationMenu` | Top-level app shell / nav bar |
| `Alert` | Inline error messages (form validation, API errors) |
| `Sonner` (Toast) | Success/failure feedback for mutations (create, delete) |

---

## Migration Plan

All seven page components and the ProtectedRoute will be updated. Inline `style={}` objects and the `App.css` file will be deleted.

### Shared app shell
- Extract a `<Layout>` component wrapping all protected pages.
- Contains: top nav (user email + logout), main content area with consistent padding.
- Uses `NavigationMenu`, `Separator`, `Button` (logout).

### Page-by-page changes

**LoginPage / RegisterPage**
- Replace inline-styled form with `Card` > `CardHeader` + `CardContent`.
- Use `Form`, `Input`, `Label` from shadcn.
- Use `Button` for submit.
- Use `Alert` for error display.

**DashboardPage**
- Wrap in `<Layout>`.
- Replace raw `<table>` with shadcn `Table` components.
- Replace inline delete button with `Button variant="destructive"`.
- Use `Sonner` toast on delete success.

**NewSourcePage / NewEndpointPage**
- Wrap in `<Layout>`.
- Replace input + button with `Form`, `Input`, `Label`, `Button`.
- Use `Alert` for validation errors.
- Use `Breadcrumb` for navigation context.

**SourceDetailPage**
- Wrap in `<Layout>`.
- Use `Card` to separate endpoints section from events section.
- Use `Table` for both endpoint list and event list.
- Use `Badge` for event status.
- Add `Breadcrumb`: Sources → [Source name].

**EventDetailPage**
- Wrap in `<Layout>`.
- Use `Card` for raw headers/body section.
- Use `Table` for delivery attempts per endpoint.
- Use `Badge` for attempt status codes (green 2xx, red 5xx, gray pending).
- Add `Breadcrumb`: Sources → [Source name] → Events → [Event id].

### Files to delete
- `src/App.css` — replaced entirely by Tailwind utilities
- Legacy CSS custom property blocks in `src/index.css` — replaced by shadcn's generated variables

---

## Critical Files

| File | Change |
|---|---|
| `frontend/vite.config.ts` | Add Tailwind Vite plugin + `@/` alias |
| `frontend/tsconfig.app.json` | Add `paths` for `@/` alias |
| `frontend/package.json` | New deps: `tailwindcss`, `@tailwindcss/vite`, `@types/node`, `clsx`, `tailwind-merge`, `class-variance-authority`, `lucide-react` |
| `frontend/src/index.css` | Replace with shadcn-generated CSS variable blocks + `@import "tailwindcss"` |
| `frontend/src/App.tsx` | Wrap routes in new `<Layout>` |
| `frontend/src/components/ui/` | All shadcn-generated components (new directory) |
| `frontend/src/pages/*.tsx` | All 7 pages migrated |
| `frontend/src/App.css` | Delete |
| `frontend/components.json` | New (shadcn config) |

---

## Verification

1. `npm run dev` starts without errors; hot reload works.
2. `npm run build` completes and outputs to `../public/build`.
3. Visually check every route: `/login`, `/register`, `/`, `/sources/new`, `/sources/:id`, `/sources/:id/endpoints/new`, `/sources/:id/events/:id`.
4. Toggle OS dark/light mode — theme switches correctly.
5. Submit a form with invalid data — `Alert` component displays error.
6. Delete a source — `Sonner` toast appears.
7. TypeScript: `npx tsc --noEmit` passes with zero errors.
