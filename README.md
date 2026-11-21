# XHopy E-Commerce Platform

Production-oriented modular monolith for the **XHopy** e-commerce platform, built with **Laravel 13**, **PHP 8.3+**, **Vue 3 + TypeScript**, **Inertia.js 3**, **Tailwind CSS v4**, **shadcn-vue**, and **MySQL 8.4 LTS**.

All engineering work in this repository is governed by [`Skills/xhlopy-ecommerce-engineering-skill/SKILL.md`](Skills/xhlopy-ecommerce-engineering-skill/SKILL.md) and [`docs/adr/001-technology-stack-and-modular-monolith.md`](docs/adr/001-technology-stack-and-modular-monolith.md).

---

## Repository Directory Structure

The repository separates backend and frontend concerns into dedicated top-level folders while preserving seamless **Inertia.js 3** and **Laravel Wayfinder** integration:

```text
Noly/
├── backend/                     # Laravel 13 Backend (PHP 8.3+, Pest 5, Larastan, Pint)
│   ├── app/
│   │   ├── Domain/              # Modular monolith domain modules:
│   │   │   ├── Admin/
│   │   │   ├── Cart/
│   │   │   ├── Catalog/
│   │   │   ├── Checkout/
│   │   │   ├── Customer/
│   │   │   ├── Identity/
│   │   │   ├── Inventory/
│   │   │   ├── Orders/
│   │   │   ├── Payments/
│   │   │   └── Promotions/
│   │   ├── Http/Controllers/    # Thin Inertia & webhook controllers
│   │   ├── Models/
│   │   └── Providers/
│   ├── config/
│   ├── database/
│   ├── public/                  # Web root (Vite outputs compiled assets to public/build)
│   ├── resources/views/         # Root Inertia Blade view (app.blade.php)
│   ├── routes/
│   ├── tests/                   # Pest 5 Unit, Feature, and Architecture tests
│   ├── artisan
│   └── composer.json
├── frontend/                    # Vue 3 + TypeScript Modular Frontend (Inertia 3, Tailwind v4, shadcn-vue)
│   ├── e2e/                     # Playwright E2E browser tests
│   ├── scripts/                 # Module boundary enforcement checks
│   ├── src/
│   │   ├── actions/             # Auto-generated Wayfinder controller actions
│   │   ├── modules/             # Isolated domain modules (public barrels via index.ts):
│   │   │   ├── account/         # Auth, 2FA, passkeys, profile, security, dashboard
│   │   │   ├── admin/           # Back-office operations
│   │   │   ├── cart/            # Cart button, drawer, and cart summary state
│   │   │   ├── catalog/         # Product cards, pricing, rating, quantity, pagination
│   │   │   ├── checkout/        # Checkout workflow
│   │   │   └── storefront/      # Storefront shell, header/footer, categories, Daily Interest
│   │   ├── routes/              # Auto-generated Wayfinder typed routes
│   │   ├── shared/              # Cross-module UI primitives (shadcn-vue), layouts, composables, lib, types
│   │   ├── styles/app.css       # Tailwind CSS v4 & XHopy brand tokens
│   │   └── app.ts               # Dynamic Inertia 3 module page & layout resolver
│   ├── package.json
│   ├── playwright.config.ts
│   ├── tsconfig.json
│   └── vite.config.ts
├── docs/adr/                    # Architecture Decision Records
└── Skills/                      # XHopy E-Commerce Engineering Skill
```

---

## Local Setup

```bash
# 1. Backend setup (from /backend)
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# 2. Frontend setup (from /frontend)
cd ../frontend
npm ci
npm run build
```

## Running Locally

```bash
# Terminal 1 — Start frontend Vite dev server (with Wayfinder & Inertia SSR)
cd frontend
npm run dev

# Terminal 2 — Start Laravel backend (if not served via Herd)
cd backend
php artisan serve
```

Then visit the Laravel URL (e.g. `http://127.0.0.1:8000` or `http://xhopy.test`). Vite (`http://localhost:5173`) provides hot module replacement automatically through `backend/public/hot`.

---

## Quality Gates

```bash
# Full CI check from /backend (runs frontend lint + vue-tsc + Pint + Larastan + Pest)
cd backend && composer ci:check

# Individual Backend Checks (run inside /backend)
composer lint:check          # Laravel Pint formatting check
composer types:check         # Larastan (PHPStan level 7)
./vendor/bin/pest            # Pest 5 unit, feature, and architecture tests

# Individual Frontend Checks (run inside /frontend)
npm run check                # Frontend formatting & lint check (vp check)
npm run types:check          # Strict TypeScript check (vue-tsc --noEmit)
npm run build                # Production bundle build (outputs to ../backend/public/build)
npm run test:e2e             # Playwright browser E2E smoke tests
```

---

## Operations, Security & Launch Readiness

See [`docs/launch-readiness-checklist.md`](docs/launch-readiness-checklist.md) for the full Batch 7 Security Review, Backup/Restore Runbook, and Production Launch Checklist.

```bash
# Assign a platform role (customer, staff, admin) to an existing account
cd backend
php artisan xhopy:user-role user@example.com admin

# Run launch readiness & inventory ledger consistency checks
php artisan xhopy:launch-check

# Create an AES-256 encrypted, SHA-256 checksummed database backup in private storage
php artisan xhopy:backup

# Verify backup integrity and run a non-destructive transactional restore simulation
php artisan xhopy:backup-verify <backup_filename> --restore-test
```

### Health Probes
- `GET /up` — Liveness probe
- `GET /health/ready` — Readiness probe (verifies database, cache, and storage with `X-Request-Id` correlation header)