# Architecture Plan — Happy Journy TukTuk Rental

## 1. Stack summary

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 12, PHP 8.3+ | `declare(strict_types=1)` everywhere, typed properties/returns |
| Modules | `nwidart/laravel-modules` | one module per bounded context (table below) |
| Views | Blade only | no Vite/Node; components under `Core` module |
| CSS | Hand-written, token-based, in `public/assets/css/` | no framework, no CDN Tailwind |
| JS | Alpine.js (`@alpinejs/csp` build, single vendored file) + vanilla JS | vendored at `public/assets/vendor/alpine.min.js` — deliberately the CSP-safe build, not regular Alpine: regular Alpine's expression evaluator requires `script-src 'unsafe-eval'` to function at all, which this project's nonce-based CSP (`Modules\Core\Http\Middleware\SecurityHeaders`) never grants. Alpine is used only for small, self-contained interactions (mobile nav, FAQ accordion, the booking review page's price recalculation); anything simpler (cookie consent, the language-switcher dropdown) is plain vanilla JS instead. |
| DB | MySQL 8, InnoDB, `utf8mb4_unicode_ci` | all datetimes stored UTC |
| Queue | `database` driver by default, swappable to `redis` via `.env` | scheduler-driven worker (§9) |
| Sessions | `database` driver | enables the admin session-management screen |
| Cache | `file`/`database` by default, swappable to `redis` via `.env` | tagged cache only used when driver supports it (Redis); file/database fallbacks avoid tags |

## 2. Request lifecycle

```
Request
 → LocaleMiddleware (resolve /{locale}/... , set app()->setLocale(), Carbon locale, cookie)
 → SecurityHeadersMiddleware (CSP nonce, HSTS, etc.)
 → (admin routes only) Auth + 2FA + Role/Permission + IP allow-list + Session idle-timeout
 → Route → Controller (thin) → FormRequest (validates + authorizes) → Service class (business logic)
 → Service uses Repository (only where a query is reused/complex) + fires Domain Events
 → Listeners queue Notifications / write ActivityLog / invalidate cache tags
 → Controller returns a View (Blade) or JSON (for the lightweight pricing/availability endpoints)
```

Controllers never contain booking, pricing, or availability logic — that lives in `AvailabilityService` and `BookingService` (Booking/Availability modules), per the brief's hard requirement in §6. Controllers only: validate via FormRequest, call one service method, return a response.

## 3. Module anatomy (nwidart/laravel-modules)

Every module follows the same skeleton so any engineer can navigate any module identically:

```
Modules/<Name>/
  Config/config.php
  Console/                     (artisan commands owned by this module)
  Database/Migrations/
  Database/Seeders/
  Database/Factories/
  Entities/ (Models)
  Events/
  Listeners/
  Jobs/
  Http/Controllers/Front/      (public site)
  Http/Controllers/Admin/      (control-panel)
  Http/Controllers/Api/        (JSON endpoints, e.g. pricing/availability)
  Http/Requests/
  Http/Middleware/
  Policies/
  Services/                    (business logic — the only place with rules)
  Repositories/                (only where warranted)
  Providers/<Name>ServiceProvider.php
  Resources/views/front/
  Resources/views/admin/
  Resources/lang/{en,de,ru,fr}/
  Routes/web.php               (public, locale-prefixed)
  Routes/admin.php             (control-panel prefix)
  Routes/api.php
  Tests/Unit/
  Tests/Feature/
```

Cross-module communication happens through **service method calls** (for synchronous needs, e.g. `BookingService` calls `AvailabilityService::lockAndReserve()`) or **events** (for decoupled side effects, e.g. `BookingConfirmed` → `Notification` module listener sends the email, `Report` module listener updates cached stats). A module is never allowed to reach into another module's Eloquent models directly for writes — only through its public Service API. Reads of simple lookup data (e.g. `Fleet\Entities\Vehicle`) are allowed directly since Eloquent models are the module's public read contract.

## 4. Localization routing (no code changes to add a language)

- `locales` table drives everything: adding "it" (Italian) in the admin immediately makes `/it/...` routable.
- Route model binding uses a custom `LocaleGroupRegistrar` that, on `RouteServiceProvider::boot()`, reads active locales from cache (`locales:active`, invalidated on model save) and registers a `Route::group(['prefix' => $code, 'middleware' => 'locale:'.$code], ...)` for each one, `require`-ing each module's `Routes/web.php` inside it.
- Translated slugs are **not** hardcoded in route files. Each translatable model (Package, Vehicle, Page, BlogPost, VehicleCategory) resolves via a single catch-all-per-type route (e.g. `Route::get('{slug}', [PackageController::class,'show'])`) that looks the slug up in `route_slugs` (locale + slug unique) — see §6 of the DB schema doc. This is what lets `/de/tuk-tuk-mieten` and `/en/rent-a-tuk-tuk` resolve to the same `Package` without per-language route definitions.
- First-visit detection: `Accept-Language` parsed only when no `locale` cookie is present **and** the User-Agent does not match a known bot list (`spatie/laravel-crawler-detect` avoided — implemented as a small static regex list of major crawler UAs to keep dependencies down, since this is a low-maintenance, rarely-changing list). Bots always get the default locale with `hreflang` links to the rest, never a redirect.

## 5. Design tokens (CSS)

`public/assets/css/tokens.css` defines custom properties (`--color-*`, `--space-*`, `--radius-*`, `--font-*`, fluid type via `clamp()`), imported first by every page stylesheet. Component CSS files are one-per-Blade-component under `public/assets/css/components/`, concatenated+minified by the `assets:minify` artisan command (backed by `matthiasmullie/minify`) into `public/assets/css/build/app.min.css`, referenced via a `mix()`-style Blade helper `asset_v('assets/css/build/app.min.css')` that appends `?v=<filemtime>`.

## 6. Booking-conflict architecture (headline requirement)

Summarized here; full detail lands in the Booking-module build in Phase 4. The schema decisions are already made now because they affect every other module's foreign keys:

- **Source of truth**: `vehicle_reservation_slots` — one row per vehicle per reserved calendar day (day-granularity slot, configurable to sub-day slots later via a `slot_unit` setting without a schema change, since the column is just `slot_date` + an optional `slot_period` tinyint for AM/PM/day if ever needed). `UNIQUE (vehicle_id, slot_date)`.
- Both a `BookingHold` (temporary) and a confirmed `Booking` write their slot rows through the **same** `AvailabilityService::reserveSlots()` method, inside `DB::transaction()` with `SELECT ... FOR UPDATE` on the `vehicles` row, so admin edits and customer bookings cannot diverge in behavior.
- A duplicate-key exception on `vehicle_reservation_slots` is caught and converted into a typed `SlotConflictException` — this is the actual "impossible to double-book" guarantee, not just an application-level check (locks reduce contention; the unique index is what makes it correct even if a lock were somehow bypassed).

## 7. Assumptions made in this phase

Since the business-detail placeholders in the brief (`[City]`, `[Full address]`, `[+94 ...]`, base currency, payment gateway, deposit rule, opening hours, brand colours) are still blank, Phase 1 proceeds without them — nothing here depends on them. They **are** required before Phase 6 (Payment) and before real content is seeded in Phase 5/9. Assumed defaults, safest-professional-option, until told otherwise:

- Base currency: **USD** (foreign-tourist business, most stable for pricing display; LKR held as a display currency, not base).
- Payment gateway: build the `Payment` module against a **gateway-agnostic interface** (`PaymentGatewayContract`) with **Stripe** as the first concrete implementation (best-documented Composer SDK, strong webhook signature verification) and a `PayHereGateway` stub interface ready to fill in once PayHere merchant credentials exist. This satisfies "pluggable gateway" without blocking on a business decision.
- **Online payment is disabled at launch (confirmed by business owner, 2026-09-30): this is a new startup, pay-on-pickup only for now.** The `Payment` module, `PaymentGatewayContract`, and the Stripe implementation are still built in full (so the feature isn't a half-finished stub later) but are gated behind a single settings flag, `payment.online_enabled` (boolean, default `false`, in the `settings` table — no schema change needed to turn it on later). While the flag is off: the checkout flow skips the payment step entirely, `deposit_amount` is forced to `0` regardless of package config, and a hold converts straight to `confirmed` status instead of `pending_payment` once the driver-details/review steps are complete. The admin Settings screen still shows the payment configuration fields, but under a "Coming soon — enable once you have a payment gateway" notice, and the public checkout never renders a card form while the flag is off. Flipping the flag on later is a config change plus entering real Stripe/PayHere keys — no code changes or redeploys.
- Deposit rule (for when online payment is later enabled): **30% online at booking, balance on pickup**, configurable per-package and globally in Settings (admin can override to 100%/0%). Currently moot since `payment.online_enabled = false`.
- Booking hold expiry: **15 minutes** (as bracketed in the brief), configurable in Settings.
- Admin panel prefix: `/control-panel` (configurable via `.env` `ADMIN_PATH`).
- Languages at launch: en (default), de, ru, fr — exactly as specified; architecture supports adding more without deploys.

Please confirm or correct: business address/phone/email, base currency, chosen payment gateway(s), deposit %, opening hours, brand colours/logo before Phase 6, and ideally before Phase 5 (design tokens) if you already have brand colours.
