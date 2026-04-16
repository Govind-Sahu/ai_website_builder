# Workspace

## Overview

This workspace contains two independent projects:
1. **Laravel API** — AI Website Builder REST API (`laravel-app/`)
2. **Node.js Monorepo** — pnpm workspace with TypeScript artifacts

---

## Laravel AI Website Builder (`laravel-app/`)

### Stack
- PHP 8.2 + Laravel 11
- SQLite (dev) — swap to PostgreSQL via `.env`
- Laravel Sanctum (Bearer token auth)
- File cache (swap to Redis via `.env`)
- OpenAI GPT-3.5 with auto-mock fallback

### Key Commands
```bash
cd laravel-app
composer install          # install dependencies
php artisan migrate       # run migrations
php artisan serve --port=8000   # start server
```

### API Endpoints (port 8000)
- `POST /api/auth/register` — register
- `POST /api/auth/login` — login
- `POST /api/auth/logout` — logout (protected)
- `GET  /api/auth/me` — user profile (protected)
- `POST /api/websites/generate` — AI generate (protected, 5/day limit)
- `GET  /api/websites` — list (paginated, protected)
- `GET  /api/websites/{id}` — show (protected)
- `PUT  /api/websites/{id}` — update (protected)
- `DELETE /api/websites/{id}` — delete (protected)
- `GET  /api/websites/history` — prompt history (protected)
- `GET  /api/websites/stats` — daily stats (protected)

---

## Node.js Monorepo

## Stack

- **Monorepo tool**: pnpm workspaces
- **Node.js version**: 24
- **Package manager**: pnpm
- **TypeScript version**: 5.9
- **API framework**: Express 5
- **Database**: PostgreSQL + Drizzle ORM
- **Validation**: Zod (`zod/v4`), `drizzle-zod`
- **API codegen**: Orval (from OpenAPI spec)
- **Build**: esbuild (CJS bundle)

## Key Commands

- `pnpm run typecheck` — full typecheck across all packages
- `pnpm run build` — typecheck + build all packages
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks and Zod schemas from OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema changes (dev only)
- `pnpm --filter @workspace/api-server run dev` — run API server locally

See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details.
