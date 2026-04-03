# Plan: Phase 0 Tasks 0.1–0.3 — Project Scaffolding

## Context
The repository is a clean slate (only docs: CLAUDE.md, PRD.md, TASKS.md). Tasks 0.1–0.3 scaffold the Symfony backend and React/Vite frontend so all future phases have a working foundation to build on.

## Steps

### Task 0.1 — Initialize Symfony 7 project
Run in the repo root (`/Users/dorianneto/http/webhookapp`):
```bash
symfony new . --version="7.*" --webapp
```
This scaffolds a full Symfony webapp (router, twig, doctrine, security, etc.) directly into the current directory alongside the existing docs files.

### Task 0.2 — Initialize React 18 + Vite (TypeScript) frontend
```bash
npm create vite@latest frontend -- --template react-ts
cd frontend && npm install
```
Creates `frontend/` with React 18, TypeScript, and Vite. Runs `npm install` to populate `node_modules`.

### Task 0.3 — Configure Vite `build.outDir`
Edit `frontend/vite.config.ts`: add `build: { outDir: '../public/build', emptyOutDir: true }` inside the `defineConfig({...})` call.

This routes `npm run build` output to Symfony's `public/build/` directory so Symfony can serve the static assets.

## Critical Files
- `frontend/vite.config.ts` — add `build.outDir`
- `frontend/package.json` — verify React 18 dependency after scaffold

## Verification
1. `symfony new` completes with no errors; `composer.json`, `src/`, `config/`, `public/` exist.
2. `frontend/` directory exists with `package.json` listing `react ^18.x`.
3. `frontend/vite.config.ts` contains `outDir: '../public/build'`.
4. Optionally run `cd frontend && npm run build` and confirm `public/build/` is created.
