# Phase 2 Summary — Core + Localization + Auth + Admin Layout + Roles/Permissions + Security Middleware

Status: **complete, awaiting your confirmation before Phase 3** (Fleet + Package builder + Pricing + Availability engine).

## What was built this phase

A real, running Laravel 12 application — scaffolded, configured for MySQL 8, with three working modules and a full admin authentication system. Nothing here is a stub; every screen listed below actually works end to end (verified by both the automated test suite and live HTTP requests during this session).

### Project scaffolding
- Laravel 12 / PHP 8.3 installed via Composer, **no Node/Vite** (`package.json`, `vite.config.js` removed from the skeleton and every module).
- `nwidart/laravel-modules` installed and configured; `Core`, `Localization`, `Admin` modules created, each following the anatomy in [01-architecture.md](01-architecture.md) §3.
- MySQL 8 database `tuktuk_rental` configured as the default connection; tests run against in-memory SQLite instead (fast, isolated — see [README.md](../README.md)).
- Composer packages installed per [05-packages.md](05-packages.md): `nwidart/laravel-modules`, `spatie/laravel-translatable`, `spatie/laravel-permission`, `spatie/laravel-activitylog`, `pragmarx/google2fa-laravel` + `bacon/bacon-qr-code`, `matthiasmullie/minify`, `pestphp/pest` (+ Laravel plugin) replacing the default PHPUnit-only skeleton per the brief's §12 requirement.
- `pint.json` (PSR-12 + strict types) — the whole codebase passes `./vendor/bin/pint --test` cleanly.

### Core module
- `SecurityHeaders` middleware — nonce-based CSP, HSTS (on HTTPS), X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, applied globally.
- `asset_v()` Blade helper (filemtime-based cache busting, prefers a minified `public/assets/build/` file when present) and `csp_nonce()` helper.
- `assets:minify` artisan command — pure-PHP CSS/JS combination and minification via `matthiasmullie/minify`, no build step.
- Base Blade layout (`<x-core::layouts.master>`) with named `styles`/`scripts` slots, consumed by the Admin layout and by the placeholder public homepage.
- `apiSuccess`/`apiError` response macros for the JSON endpoints later modules will expose.

### Localization module
- Schema: `locales`, `route_slugs`, `missing_translations_log` (exactly as designed in [03-database-schema.md](03-database-schema.md)).
- `Locale` model with an indefinite, auto-invalidating cache of active locales (`Locale::activeCached()`).
- `SetLocale` middleware + `LocaleRouteRegistrar`: a single generic `/{locale}/...` route group (pattern-matched, not hardcoded to whatever locales happen to exist at boot — see the inline comment in the registrar for why that distinction matters) that any module plugs into via its own `routes/locale.php`. Unknown/inactive locale codes 404 at the middleware layer, which is re-checked on every request.
- Root `/` entry point (`RedirectLocaleController`): cookie → bot (always default, never guessed) → `Accept-Language` detection → default, redirecting exactly once and remembering the choice — matches the brief's §3 requirement precisely, including "never auto-redirect search engine bots."
- Language switcher Blade component (path-segment swap; will become slug-aware once translatable models with `route_slugs` entries exist in Phase 3+).
- Seeded with the four required locales: English (default), German, Russian, French.
- Admin CRUD for locales (list, create, edit, set-default, delete-with-default-protection), authorized via a dedicated `LocalePolicy`.
- A placeholder "coming soon" public homepage (Core module) proves the whole locale-routing mechanism end-to-end in all 4 languages; replaced by the real homepage in Phase 5.

### Admin module
- **Authentication**: login form, rate-limited (`throttle:10,1`) + a `login_attempts` audit table backing a configurable lockout (5 attempts / 15 minutes, per email *and* per IP independently), inactive users rejected, session regenerated on login.
- **Mandatory 2FA**: TOTP via `pragmarx/google2fa-laravel`, enforced for the **Super Admin** role (configurable list in `.env`/`config/admin.php`). Forced enrollment (QR + manual secret) on first login for enforced roles, one-time recovery-codes display, per-session re-challenge (having 2FA "on" doesn't skip the challenge on a new session), recovery-code fallback at the challenge screen.
- **Session hardening**: idle timeout (20 min) and absolute lifetime (8 hours) enforced by `EnsureAdminSessionIsFresh`, both configurable via `.env`. (The full session *management* screen — list/revoke active sessions — is explicitly a Phase 7 "system tools" deliverable per the brief's own phase plan, not Phase 2.)
- **IP allowlist / blocked-IP blocking**: `RestrictAdminIpAllowlist`, off by default, configurable; applies to the login page itself, not just authenticated routes (a gap caught and fixed during this session's own testing).
- **Roles & permissions**: `spatie/laravel-permission` wired in; the four brief-named roles seeded (Super Admin, Manager, Booking Agent, Content Editor) with a small, currently-real permission set (`locales.view/manage`, `users.view/manage`) that every future module will extend the same way.
- **User management**: full CRUD for admin users (create/edit/deactivate, role assignment), policy-protected (`UserPolicy`), Super-Admin-only by permission.
- Hand-written, responsive (mobile-first, 44px touch targets, no framework) admin CSS and a sidebar layout with permission-aware nav.
- Dashboard placeholder (real KPIs land in Phase 7 once Booking/Fleet/Payment exist).

### Security middleware (brief §8, the pieces that apply before real user-facing forms exist)
CSRF (Laravel default, unchanged), nonce-based CSP, HSTS, standard security headers, rate limiting on login, mass-assignment protection (`$fillable` throughout), non-sequential thinking already designed into the `bookings.reference` scheme for Phase 4, encrypted casts on 2FA secrets/recovery codes, `bcrypt` (rounds configurable), soft-deletes on `users` (no hard data loss from an accidental "remove user" click).

## Verified, not just written

Ran live against this session's WAMP MySQL instance and the built-in PHP server:
- `/` → 302 to `/en` (or best `Accept-Language` match, or the default for a crawler UA) → 200 with translated content and correct `lang=""` attribute, in all 4 seeded languages.
- Admin login → forced 2FA setup (QR renders, TOTP confirms) → recovery codes shown once → dashboard reachable → locales CRUD reachable and functional.
- Guest hitting any admin route → redirected to login, never a 500 or a blank page.
- `./vendor/bin/pest`: **22/22 passing** (locale routing/redirect/bot-handling, admin auth + lockout, 2FA enforcement including the "new session re-challenges" case, and role-based authorization down to "Content Editor can view locales but not create one").
- `./vendor/bin/pint --test`: clean.

## A real bug found and fixed during this phase (worth flagging)

`nwidart/laravel-modules` registers each module's service providers *after* the framework has already finished its normal boot cycle, which means a module's own nested `RouteServiceProvider` can end up building its route groups (via `Route::prefix(config(...))`) *before* that same module's `boot()` — where its `config/config.php` gets merged — has actually run. This silently dropped the `control-panel/` prefix from every one of Admin's own routes (but not `Localization`'s, which reads the *same* config key from a module that had already fully finished loading). Fixed by merging Admin's config explicitly at the top of `AdminServiceProvider::register()`, before its sub-providers register. Documented in-code since this will bite again the moment a new module reads its own config while building routes.

## Assumptions made this phase

| Item | Assumption | Reasoning |
|---|---|---|
| Auth guard | Single `web` guard for staff, no separate `admin` guard | No customer-facing auth exists yet (Phase 5); introducing a second guard now would be speculative. Revisit when the Customer module adds guest/customer login. |
| Admin UI language | English only, no 4-language translation of the admin panel itself | The brief's 4-language requirement targets the **customer-facing** site; the admin back-office is operated by the business's own staff. Flag if you want the admin panel itself translated too. |
| `settings` DB table | Not created yet | Every Phase-2 configuration value (admin path, session timeouts, 2FA-enforced roles, IP allowlist) is `.env`/config-file based, which is all that's needed so far. The brief's full Settings *admin screen* (business info, currencies, tax, deposit %, email templates) is a Phase 7 deliverable — building the table now would be speculative schema ahead of the feature that uses it. |
| Recovery codes storage | `encrypted:array` cast (reversible), not one-way hashed | Lets the confirmation flow work simply; protected by `APP_KEY` the same way every other encrypted column is. Flag if you'd prefer one-way hashing (trades away the ability to ever re-display them, which isn't needed since they're shown once at generation time anyway — encrypted was the pragmatic choice here, not a security gap). |
| `laravel/sail` | Removed from `composer.json` | It's a Docker dev-environment package; this project's constraint is "small VPS or shared hosting," and local dev here uses the already-installed WAMP stack. No functional loss. |

## Next step

On your go-ahead, Phase 3 begins: **Fleet + Package builder + Pricing + Availability engine**, with the first real domain data (vehicles, packages, seasonal pricing) and the first tests for the pricing calculator described in [03-database-schema.md](03-database-schema.md).
