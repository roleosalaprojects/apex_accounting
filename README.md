# Apex Accounting

Double-entry accounting and BIR-compliant bookkeeping for Philippine SMBs — part of the Apex ecosystem (POS, HRMS, and accounting share one integration API).

Built on **Laravel 13 + Filament 4**, with multi-company tenancy, an immutable posting ledger, AR/AP, banking, inventory (weighted-average COGS), fixed assets, recurring transactions, and BIR books/reports.

## Stack

- PHP 8.4 · Laravel 13 · Filament 4 (admin panel)
- Laravel Passport (ecosystem integration API) · spatie/laravel-permission (RBAC) · spatie/laravel-data
- SQLite for local/dev and tests; MySQL/Postgres in production
- Pest (tests) · Pint (style) · PHPStan (static analysis)

## Architecture highlights

- **Posting chokepoint** — every financial document posts through `App\Actions\Ledger\PostJournalEntry`: balanced, period-checked, audited, idempotent-safe.
- **Multi-company tenancy** — companies are Filament tenants; data is company-scoped via a global scope and per-request company context.
- **Integration API** (`routes/api.php`, `/api/v1`) — Passport-scoped, idempotent endpoints for posting journal entries / invoices from Apex POS and Charlie HRMS.

## Local setup

```bash
composer install
npm ci && npm run build          # builds the Filament theme (required — the panel renders it)
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan passport:keys
php artisan db:seed --class=DemoCompanySeeder   # golden-master demo company (Dari Ventures Corp.)
php artisan serve
```

The admin panel is at `/admin`.

## Quality gates

```bash
php artisan test                                   # Pest suite
vendor/bin/pint --test                             # code style
vendor/bin/phpstan analyse --memory-limit=1G       # static analysis
```

CI runs all three on every push and pull request (`.github/workflows/ci.yml`).
