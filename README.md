# Miranda Tuk Tuk Rental

Laravel 12 / PHP 8.3, modular (`nwidart/laravel-modules`), no Node/Vite — see [docs/01-architecture.md](docs/01-architecture.md) for the full architecture and [docs/00-phase-1-summary.md](docs/00-phase-1-summary.md) for the project's phased build plan.

This README covers local development only. The production installation/deployment guide, backup/restore procedure, and "how to add a language / payment gateway" guides are a later-phase deliverable (§13 of the project brief) and will replace this section once the app is feature-complete.

## Requirements

- PHP 8.3+ with `pdo_mysql`, `pdo_sqlite` (tests), `mbstring`, `gd` extensions
- MySQL 8 (InnoDB, `utf8mb4_unicode_ci`)
- Composer 2
- No Node.js / npm required, ever — assets are hand-written and minified via `php artisan assets:minify`

## Local setup

```bash
composer install
cp .env.example .env   # then set DB_* and other values — see .env for the full reference
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The seeder creates one Super Admin for local development only (`app()->isProduction()` guard in `AdminDatabaseSeeder`):

- URL: `http://127.0.0.1:8000/control-panel/login`
- Email: `owner@mirandatuktuk.example`
- Password: `ChangeMe!12345`
- You will be forced into mandatory 2FA setup on first login (Super Admin is a 2FA-enforced role) — scan the QR code with any TOTP app.

## Running tests

```bash
./vendor/bin/pest
```

Tests run against an in-memory SQLite database (see `phpunit.xml`), independent of your local MySQL `DB_DATABASE`.

## Code style

```bash
./vendor/bin/pint        # auto-fix
./vendor/bin/pint --test # check only, no changes
```

## Project layout

Domain code lives under `Modules/<Name>/`, one module per bounded context (Core, Localization, Admin, and more added in later phases — see [docs/02-modules.md](docs/02-modules.md)). The root `app/` directory is intentionally minimal — just the base `User` model and the empty `Controller` base class every module's controllers extend.
