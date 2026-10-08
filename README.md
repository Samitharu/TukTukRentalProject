# Happy Journy TukTuk Rental

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

The seeder creates two staff accounts for local development only (`app()->isProduction()` guard in `AdminDatabaseSeeder`). Both log in at `http://127.0.0.1:8000/control-panel/login`:

| Role | Email | Password |
| --- | --- | --- |
| Super Admin | `owner@mirandatuktuk.example` | `ChangeMe!12345` |
| Admin (formerly Manager) | `manager@mirandatuktuk.example` | `ChangeMe!12345` |

The Super Admin is forced into mandatory 2FA setup on first login (a 2FA-enforced role) — scan the QR code with any TOTP app. The Admin is not, unless added to `TWO_FACTOR_ENFORCED_ROLES`.

## Email

When a booking is placed (online or a manual booking in the control panel), two emails go out:

- **Customer confirmation** — to the customer, in the language they booked in, with their booking details, pickup location map and a link to the receipt. Replies go to `BUSINESS_EMAIL`.
- **New booking alert** — to every address in `BOOKING_ADMIN_NOTIFICATION_EMAILS` (falls back to `BUSINESS_EMAIL`). Replies go to the customer.

Both are **queued**, so the booking never waits on the mail server — but that means a queue worker must be running, or nothing is sent.

### Sending through the company Gmail

1. In the company Google account: turn on **2-Step Verification**, then create an **App Password** (Google Account → Security → App passwords). Copy the 16-character password.
2. In the server `.env`:

   ```dotenv
   MAIL_MAILER=smtp
   MAIL_SCHEME=null
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=company@gmail.com
   MAIL_PASSWORD="abcd efgh ijkl mnop"
   MAIL_FROM_ADDRESS=company@gmail.com
   MAIL_FROM_NAME="${APP_NAME}"

   BUSINESS_EMAIL=company@gmail.com
   BOOKING_ADMIN_NOTIFICATION_EMAILS=admin@gmail.com
   APP_URL=https://happyjourneytuktukrental.com   # links in emails are built from this
   ```

   Then `php artisan config:cache`.
3. Run a queue worker permanently (systemd example — adjust the user and path):

   ```ini
   # /etc/systemd/system/tuktuk-queue.service
   [Unit]
   Description=TukTuk queue worker
   After=network.target mysql.service

   [Service]
   User=www-data
   WorkingDirectory=/var/www/tuktuk
   ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=1 --max-time=3600
   Restart=always

   [Install]
   WantedBy=multi-user.target
   ```

   ```bash
   sudo systemctl daemon-reload && sudo systemctl enable --now tuktuk-queue
   ```

   After every deploy, run `php artisan queue:restart` so the worker picks up the new code.
4. Test it: `php artisan tinker` → `Mail::raw('Test', fn ($m) => $m->to('you@example.com')->subject('Test'));`

Gmail allows roughly 500 emails a day from one account — plenty for booking emails. Failed sends are retried 5 times over about an hour and then appear in `php artisan queue:failed`.

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
