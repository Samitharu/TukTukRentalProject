# Composer Package List & Justification

Rule from the brief: only well-maintained packages, and every one must be justified. "No npm/Node/Vite" means nothing here has a JS-build-step dependency — every package below is a pure-PHP Composer package or ships a single pre-built vendored JS/CSS file we download once.

## Framework-adjacent (required by the brief explicitly)

| Package | Why |
|---|---|
| `nwidart/laravel-modules` | Brief-mandated modular architecture (§1). Actively maintained, Laravel-12-compatible, no runtime dependency beyond Composer autoloading — doesn't add request overhead. |
| `spatie/laravel-translatable` | Brief-mandated (§3) JSON-column translatable content. Spatie packages are the de-facto standard for this in Laravel, actively maintained, zero JS/build footprint (pure Eloquent cast). Avoids hand-rolling translation JSON handling across 8+ translatable models. |
| `spatie/laravel-permission` | Brief-mandated (§5) roles/permissions. Widely adopted, actively maintained, integrates with Laravel's native `Gate`/`Policy` system so `@can` in Blade and `authorize()` in FormRequests work unchanged — no custom RBAC layer to maintain. |
| `pragmarx/google2fa-laravel` + `bacon/bacon-qr-code` | Brief-mandated (§5) mandatory Super Admin 2FA (TOTP). `google2fa-laravel` implements RFC 6238 TOTP correctly (the hard, security-sensitive part not worth hand-rolling); `bacon/bacon-qr-code` renders the setup QR code as SVG server-side, no JS QR library needed. |
| `spatie/laravel-activitylog` | Brief-mandated (§5) full admin audit log. Handles polymorphic "who did what to which model" logging with a maintained query API for the admin activity screen, instead of a bespoke audit table + manual logging calls scattered across every controller. |
| `spatie/laravel-backup` | Brief-mandated (§5/§9) scheduled DB+file backups with retention. Supports local + common cloud disks (works with Laravel's Filesystem abstraction, so a small VPS can back up to local disk or S3-compatible storage without code changes), integrates with the scheduler out of the box. |
| `barryvdh/laravel-dompdf` | Brief-mandated (§11) PDF invoices. Wraps `dompdf/dompdf`, renders Blade views to PDF — reuses the same Blade templating/translation system already in place for invoices, no separate templating language to maintain for PDFs. |
| `mews/purifier` | Brief-mandated (§8) rich-text sanitization ("sanitised rich text via mews/purifier" — named explicitly). Wraps `ezyang/htmlpurifier`, the standard whitelist-based HTML sanitizer for PHP; used on every admin rich-text field (package descriptions, CMS pages, blog body) before storage. |
| `matthiasmullie/minify` | Brief-mandated (§1) pure-PHP CSS/JS minification for the `assets:minify` artisan command — no Node/Vite dependency, exactly the constraint the brief sets. |

## Additional packages (not named in the brief, added and justified individually)

| Package | Why it's needed | Why this one specifically |
|---|---|---|
| `stripe/stripe-php` | First concrete `PaymentGatewayContract` implementation (§9 assumption in architecture doc — gateway TBD, Stripe chosen as the safe default to build the interface against). | Official Stripe SDK, handles webhook signature verification (`Stripe\Webhook::constructEvent`) which is a brief-mandated security requirement (§8) — hand-rolling HMAC verification for a payment webhook is exactly the kind of security-sensitive code that shouldn't be reinvented. |
| `propaganistas/laravel-phone` | Brief-mandated (§7) international phone validation with country code. | Wraps Google's `libphonenumber` (the actual authoritative phone-format database), exposed as a Laravel validation rule (`phone:AUTO` or per-country) — the alternative is a regex that will be wrong for many of the 190+ nationalities this site needs to accept. |
| `intervention/image` (v4, GD driver) | Brief-mandated (§9) auto-resize + WebP conversion on upload, EXIF stripping on documents (§7). | Installed as v4 (latest stable at implementation time; the Phase 1 plan anticipated v3, corrected here — same driver-based architecture). Supports the GD extension (present on virtually all shared hosting, unlike Imagick which often isn't installed) while still producing WebP — matches the brief's "small VPS / shared hosting" constraint directly. Driver instantiated explicitly (`new Driver()`) rather than relying on auto-detection, per Phase 3's `Fleet` implementation. |
| `spatie/laravel-sitemap` | Brief-mandated (§10) per-locale sitemap index with image entries, auto-regenerated. | Hand-rolling XML sitemap generation (namespaces, image extension, sitemap-index nesting) is solved, well-tested territory; this package supports all of it and integrates with the scheduler for regeneration. |
| `jenssegers/agent` | Brief-mandated (§5) admin session list showing device/browser per session. | Parses the `user_agent` column already stored by Laravel's stock `sessions` table into "Chrome on Windows" style output — small, focused, no extra runtime dependency (no external API calls, pure UA-string parsing). |
| `pestphp/pest` + `pestphp/pest-plugin-laravel` | Brief-mandated (§12) Pest test suite. | Pest is explicitly named in the brief; it's Laravel-first-party-adjacent (maintained by the Laravel ecosystem team) and gives the concise syntax needed for the concurrency/conflict test scenarios in §6 (`it('rejects overlapping bookings')`). |
| `laravel/pint` | Brief-mandated (§1) PSR-12 enforcement + code-style guide. | Laravel's own official formatter, zero-config PSR-12 baseline, one command in CI/pre-commit. |

## Explicitly considered and rejected

| Package | Why not |
|---|---|
| `laravel/horizon` | Requires Redis as a hard dependency for its dashboard; the brief requires everything to work on `database` queue driver with Redis merely optional. A custom lightweight queue-monitor screen (reading `jobs`/`failed_jobs` directly, per the DB schema doc) works on both drivers with no added dependency. |
| Unofficial PayHere SDKs | No actively-maintained official Composer package exists for PayHere; the few community ones are low-adoption/stale. PayHere's integration is a signed HTTP POST + webhook, implemented directly against their documented API inside `PayHereGateway implements PaymentGatewayContract` — safer than depending on an unmaintained third-party wrapper for a payment integration. |
| `bepsvpt/secure-headers` | Brief requires nonce-based CSP tied tightly to inline-script usage across Blade views; a small hand-written `SecurityHeadersMiddleware` (~40 lines) gives full control over the nonce lifecycle per-request without an extra dependency for what's a small, stable, security-sensitive piece of code worth owning directly. |
| `stevebauman/purify` (newer alternative to `mews/purifier`) | Brief names `mews/purifier` explicitly; not substituted absent a maintenance problem with it (checked: it receives regular releases and Laravel-12 compatibility updates). |
| Cloudflare Turnstile / reCAPTCHA Composer wrappers | These are thin HTTP-call wrappers around a documented single-endpoint API; a direct `Http::post()` call in a small `BotProtection` service avoids a dependency for something that isn't meaningfully harder to call directly. |

## Rule going forward

Any package proposed in later phases will be added to this document with the same "why this, why not the alternative" justification before it's added to `composer.json`, so this file stays the single audit trail for every third-party dependency in the project.
