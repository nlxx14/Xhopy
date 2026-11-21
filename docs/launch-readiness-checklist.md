# XHopy Batch 7 — Quality, Security, and Launch Readiness Runbook

- **Status:** Active (2026-11-21)
- **Governing Standards:**
  - [`skills/xhlopy-ecommerce-engineering-skill/SKILL.md`](../skills/xhlopy-ecommerce-engineering-skill/SKILL.md)
  - [`skills/xhlopy-ecommerce-engineering-skill/references/security-standards.md`](../skills/xhlopy-ecommerce-engineering-skill/references/security-standards.md)
  - [`skills/xhlopy-ecommerce-engineering-skill/references/testing-and-release.md`](../skills/xhlopy-ecommerce-engineering-skill/references/testing-and-release.md)
  - [`docs/adr/001-technology-stack-and-modular-monolith.md`](./adr/001-technology-stack-and-modular-monolith.md)

---

## 1. Security Review Checklist

- [x] **Authentication & Session Lifecycle:**
  - Adaptive password hashing (bcrypt/Argon2id via Laravel Hashing) and production password complexity enforced (`Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()`).
  - 6-digit email verification code flow (`EmailVerificationCodeService`) with hashed storage, 15-minute expiry, and throttling (`throttle:6,1`).
  - Session fixation prevention, active browser session inspection, and "Log out other browser sessions" (`BrowserSessionManager`).
  - Passkeys (`laravel/passkeys`) and Two-Factor Authentication (TOTP via Laravel Fortify) available in Account Security settings.
- [x] **Server-Side Role & Resource Authorization:**
  - Explicit role separation (`customer`, `staff`, `admin`) enforced via `EnsureUserHasRole` middleware and Gates (`access-backoffice`, `access-admin`).
  - Resource ownership policies (`CustomerAddressPolicy`, `OrderPolicy`, cart ownership validation in `CartController`) prevent cross-customer Insecure Direct Object Reference (IDOR) access.
  - `role` attribute is excluded from `User::$fillable` and assigned strictly through `User::assignRole()` / `UpdateUserRoleAction` to block mass-assignment privilege escalation.
- [x] **Input Validation & Output Encoding:**
  - Boundary Form Requests (`CatalogIndexRequest`, `CustomerAddressRequest`, `CheckoutOrderRequest`) and strict allowlists (`ProductSort`, `OrderStatus`, `ProductStatus`).
  - Parameterized Eloquent/Query Builder queries with strict mode enabled (`Model::shouldBeStrict(! app()->isProduction())`).
  - Vue 3 automatic template escaping across all storefront, account, and admin pages.
- [x] **CSRF, Security Headers & Correlation IDs:**
  - CSRF protection enabled on all cookie-authenticated state-changing routes (only `webhooks/paymongo` is exempted and verified via HMAC-SHA256 `Paymongo-Signature`).
  - `AddSecurityHeaders` middleware attaches `X-Request-Id`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, `Strict-Transport-Security` (in HTTPS/production), and `Content-Security-Policy`.
- [x] **Rate Limits & Abuse Controls:**
  - Login, 2FA, password reset, and 6-digit verification code endpoints throttled (`throttle:6,1`).
  - Checkout submission (`throttle:10,1`), order cancellation (`throttle:10,1`), cart/wishlist mutations (`throttle:60,1`), PayMongo webhook receiver (`throttle:60,1`), and admin bulk/import operations (`throttle:30,1`) are rate-limited.
- [x] **Payment & Webhook Authenticity:**
  - `PayMongoPaymentGateway` verifies `Paymongo-Signature` HMAC-SHA256 digest and timestamp tolerance (`300s` replay window).
  - `PaymentWebhookProcessor` enforces idempotency via unique `provider_event_id` on `payment_events` and locks order rows (`lockForUpdate()`) during payment confirmation.

---

## 2. Database Backup & Restore Verification Procedure

XHopy includes an encrypted, SHA-256 checksummed snapshot and restore verification utility (`App\Domain\Admin\Services\DatabaseBackupService`) in addition to standard MySQL physical/logical backups (`mysqldump`).

### Creating an Encrypted Snapshot
```bash
cd backend
php artisan xhopy:backup
```
- Stores an AES-256 encrypted snapshot (`storage/app/private/backups/xhopy_backup_<timestamp>.json.enc`) covering all 14 core commerce and audit tables (`categories`, `products`, `product_images`, `campaign_contents`, `customer_addresses`, `wishlist_items`, `orders`, `order_items`, `order_status_histories`, `payments`, `payment_events`, `stock_movements`, `account_audit_logs`, `admin_audit_logs`).

### Verifying Checksum & Running a Non-Destructive Restore Simulation
```bash
cd backend
php artisan xhopy:backup-verify <filename> --restore-test
```
- Decrypts the backup payload using `APP_KEY`.
- Recomputes and validates the SHA-256 digest across all table rows to detect tampering or corruption.
- Runs a transactional schema and column compatibility simulation inside a rolled-back database transaction (`DB::beginTransaction()` -> `DB::rollBack()`).

---

## 3. Pre-Launch & Staging Acceptance Checklist

Run the automated readiness command and full CI check before every staging or production release:

```bash
# 1. Automated operational & inventory ledger readiness check
cd backend
php artisan xhopy:launch-check

# 2. Full backend + frontend quality gate suite
composer ci:check
```

### Production Environment Variables Checklist (`backend/.env`)
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-production-domain.com`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_HTTP_ONLY=true`
- `SESSION_SAME_SITE=lax`
- `DB_CONNECTION=mysql` (with least-privilege application MySQL user)
- `MAIL_MAILER=smtp` and `MAIL_SCHEME=smtp` (with valid SMTP/App Password credentials)
- `PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET` configured from PayMongo dashboard

---

## 4. Health Monitoring & Rollback Plan

### Health & Readiness Endpoints
- **Liveness Probe:** `GET /up` (returns `200 OK` when the PHP/Laravel HTTP worker is responsive).
- **Readiness Probe:** `GET /health/ready` (verifies database connectivity, cache read/write, and local storage writability; returns `200 OK` with JSON status and `X-Request-Id`, or `503 Service Unavailable` when degraded).

### Rollback Plan
1. **Pre-Deployment Snapshot:** Always run `php artisan xhopy:backup` (and host-level MySQL backup) prior to running `php artisan migrate --force`.
2. **Application Code Rollback:** Redeploy the previous tagged Git commit and run `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
3. **Database Migration Rollback:** All 13 migrations in `backend/database/migrations/` implement reversible `down()` methods. For additive schema changes in production, prefer forward-compatible migrations and forward-fixes over destructive rollbacks.
4. **Inventory Reconciliation:** After any incident affecting checkout or order cancellation, run `php artisan xhopy:launch-check` or trigger **Reconcile Ledger** from `/admin/products` (`POST /admin/inventory/reconcile`).
