# Tasks — Design System with shadcn/ui

Note: npm/npx commands MUST be executed inside the `app` container with `cd frontend` first.
Example: `docker compose exec -T app bash -c "cd frontend && npm install ..."`
For npx: `docker compose exec -T app bash -c "cd frontend && node /usr/local/lib/node_modules/npm/bin/npx-cli.js shadcn@latest ..."`

## 1. Setup & Installation

- [x] Install Tailwind CSS v4 and the Vite plugin (`tailwindcss`, `@tailwindcss/vite`)
- [x] Install `@types/node` dev dependency
- [x] Update `vite.config.ts`: add Tailwind Vite plugin and `@/` path alias
- [x] Update `tsconfig.app.json`: add `paths` entry for `@/` alias
- [x] Replace `frontend/src/index.css` with `@import "tailwindcss";` (legacy CSS removed — shadcn init populated full CSS variable blocks)
- [x] Run `npx shadcn@latest init` (style: radix-nova, base color: neutral, CSS variables: yes)
- [x] Install shadcn components: `button input label form` (note: `form` has no component files in shadcn v4 — use native HTML `<form>` + Input/Label/Button)
- [x] Install shadcn components: `table badge`
- [x] Install shadcn components: `card separator breadcrumb navigation-menu`
- [x] Install shadcn components: `alert sonner`

## 2. Design Tokens

- [x] Override `--primary` in `index.css` to `#6366f1` (indigo) for both light and dark themes — light: `oklch(0.585 0.233 264.4)`, dark: `oklch(0.664 0.187 264.4)`; `--ring` updated to match
- [x] Set `--radius` to `0.5rem` in the shadcn CSS variable block

## 3. Shared Layout

- [x] Create `src/components/Layout.tsx` with top nav (user email + logout button) and main content area
- [x] Update `src/App.tsx` to wrap protected routes with `<Layout>`
- [x] Delete `src/App.css`

## 4. Page Migrations

- [x] **LoginPage** — replace inline styles with `Card`, `Input`, `Label`, `Button`, `Alert`
- [x] **RegisterPage** — replace inline styles with `Card`, `Input`, `Label`, `Button`, `Alert`
- [x] **DashboardPage** — replace raw table with shadcn `Table`; use `Button variant="destructive"` for delete; add `Sonner` toast on delete
- [x] **NewSourcePage** — replace inline styles with `Input`, `Label`, `Button`, `Alert`; add `Breadcrumb`
- [x] **NewEndpointPage** — replace inline styles with `Input`, `Label`, `Button`, `Alert`; add `Breadcrumb`
- [x] **SourceDetailPage** — use `Card` per section, shadcn `Table` for endpoints and events, `Badge` for event status, `Breadcrumb`
- [x] **EventDetailPage** — use `Card` for raw payload, shadcn `Table` for delivery attempts, `Badge` for status codes, `Breadcrumb`

## 5. Verification

- [x] All 7 routes render correctly with the new design system
- [x] Light/dark theme switches correctly on OS preference change
- [x] Form validation errors render via `Alert`
- [x] Delete action shows `Sonner` toast
- [x] `npx tsc --noEmit` passes with zero errors
