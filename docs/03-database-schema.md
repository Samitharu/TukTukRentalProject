# Database Schema — Miranda Tuk Tuk Rental

MySQL 8, InnoDB, `utf8mb4_unicode_ci`. All `*_at` datetime columns store UTC; display conversion to `Asia/Colombo` (or the customer's locale-implied timezone for emails) happens in the presentation layer only. Translatable text columns (marked **[T]**) are JSON columns consumed by `spatie/laravel-translatable`, keyed by locale code (`{"en": "...", "de": "...", "ru": "...", "fr": "..."}`).

Conventions: every table has `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `created_at`/`updated_at`; soft-deletes (`deleted_at`) only where noted. Foreign keys are always indexed automatically by InnoDB — this doc calls out only the **additional** indexes that matter, and why.

---

## Core / Localization

**locales**
`id, code (varchar 5, unique), name, native_name, is_default bool, is_active bool, sort_order int, flag_icon varchar`
- Index: none beyond PK/unique(code) — tiny, always read through cache.

**route_slugs** — the single place every translated URL slug lives, so any translatable model gets locale-aware routing without per-model slug columns.
`id, model_type varchar, model_id bigint, locale varchar(5), slug varchar(191)`
- `UNIQUE (model_type, locale, slug)` — this **is** the routing index: `Package::where slug='tuk-tuk-mieten' locale='de'` resolves in one lookup, and it's what guarantees two Packages can't collide on the same German slug.
- `INDEX (model_type, model_id, locale)` — reverse lookup ("what's this Package's slug in French") used when rendering `hreflang` tags and the admin's live-preview.

**missing_translations_log**
`id, model_type, model_id, locale, field, created_at`
- `INDEX (locale, created_at)` — admin dashboard widget queries "missing translations in the last 30 days" per locale.

**settings**
`id, key varchar(191) unique, value json, group varchar(60), is_public bool`
- Cached wholesale (`settings:all`) — no query-pattern index needed beyond the unique key.

**redirects**
`id, from_path varchar(255), locale varchar(5) nullable, to_path varchar(255), status_code smallint default 301, hits int default 0, created_at`
- `UNIQUE (from_path, locale)` — prevents duplicate redirect rules and is the exact lookup the 404 handler performs before rendering a real 404.

---

## Fleet

**vehicle_categories**
`id, name [T], description [T] nullable, icon varchar, sort_order int, is_active bool`

**vehicles**
`id, category_id FK→vehicle_categories, name [T], plate_no varchar(20) unique, model varchar, year smallint, colour varchar, seats tinyint, transmission enum(manual,automatic), fuel_type enum(petrol,diesel,electric), features json, description [T] nullable, base_location_id FK→business_locations, status enum(active,maintenance,retired) default active, deleted_at`
- `UNIQUE (plate_no)` — real-world uniqueness constraint, also blocks accidental duplicate fleet entry.
- `INDEX (category_id, status)` — the exact filter the public fleet-list and the Package "allowed categories" resolver use ("active vehicles in category X").
- `INDEX (status)` — admin fleet list and the automatic-vehicle-assignment query (§ Booking) both filter by status=active first.

**vehicle_images**
`id, vehicle_id FK→vehicles, path varchar, is_primary bool, sort_order int`
- `INDEX (vehicle_id, sort_order)` — gallery render order, single query.

**vehicle_maintenance_logs**
`id, vehicle_id FK→vehicles, type varchar, description text, cost decimal(10,2) nullable, starts_at datetime, ends_at datetime, created_by FK→users`
- `INDEX (vehicle_id, starts_at, ends_at)` — this table feeds the Availability engine's blackout check for a vehicle in a date range; the composite index makes that an index range scan instead of a table scan.

---

## Package / Pricing

**packages**
`id, name [T], description [T], inclusions [T] json, exclusions [T] json, pricing_model enum(per_day,per_week,per_month,fixed_bundle,tiered), min_days smallint, max_days smallint nullable, included_km int nullable, deposit_amount decimal(10,2) nullable, deposit_is_percent bool, cancellation_policy [T], is_active bool, is_featured bool, sort_order int, valid_from date nullable, valid_until date nullable, deleted_at`
- `INDEX (is_active, is_featured, sort_order)` — the home-page "featured packages" and the public package-list query, in the exact order they filter/sort.
- `INDEX (valid_from, valid_until)` — validity-window filtering done every time a package list is rendered (excludes expired/not-yet-live packages).

**package_vehicles** (pivot) `package_id FK, vehicle_id FK` — `PRIMARY KEY (package_id, vehicle_id)`
**package_categories** (pivot) `package_id FK, category_id FK` — `PRIMARY KEY (package_id, category_id)`

**package_pricing_tiers**
`id, package_id FK→packages, min_days smallint, max_days smallint nullable, price decimal(10,2)`
- `UNIQUE (package_id, min_days)` — a package can't define two tiers starting at the same day count; the admin "no gaps/overlaps" validator additionally checks continuity in the service layer (a DB constraint alone can't express "no gaps").
- `INDEX (package_id, min_days, max_days)` — the price-lookup query ("which tier covers a 5-day rental") range-scans this.

**package_seasons**
`id, package_id FK→packages, name varchar, starts_on date, ends_on date, price_modifier_type enum(fixed,percent), price_modifier_value decimal(10,2), weekday_mask tinyint, priority smallint default 0`
- `INDEX (package_id, starts_on, ends_on)` — the pricing calculator's "which seasons overlap this booking's date range" query, run on every price calculation (including the live JSON recalculation endpoint), so this index is on the hot path.

**addons**
`id, name [T], description [T] nullable, price decimal(10,2), pricing_unit enum(flat,per_day), max_quantity tinyint default 1, is_active bool, sort_order int`

**package_addons** (pivot) `package_id FK, addon_id FK, is_included bool` — `PRIMARY KEY (package_id, addon_id)`

**coupons**
`id, code varchar(40) unique, type enum(fixed,percent), value decimal(10,2), min_days smallint nullable, valid_from date nullable, valid_until date nullable, usage_limit int nullable, usage_count int default 0, applies_to json nullable, is_active bool`
- `UNIQUE (code)` — also the exact lookup at checkout (case-insensitive collation handles case-folding).

**currencies**
`id, code varchar(3) unique, symbol varchar(5), is_base bool, is_active bool, decimal_places tinyint default 2`

**exchange_rates**
`id, currency_id FK→currencies, rate decimal(12,6), fetched_at datetime`
- `INDEX (currency_id, fetched_at)` — "latest rate for currency X" query (`ORDER BY fetched_at DESC LIMIT 1`) uses this instead of scanning history.

---

## Availability & Booking — the critical section (brief §6)

**business_locations**
`id, name [T], address text, lat decimal(10,7) nullable, lng decimal(10,7) nullable, is_pickup_point bool, is_active bool`

**delivery_zones**
`id, name [T], extra_fee decimal(10,2), is_active bool`

**availability_blackouts**
`id, vehicle_id FK→vehicles nullable (null = business-wide), starts_on date, ends_on date, reason varchar, created_by FK→users`
- `INDEX (vehicle_id, starts_on, ends_on)` — same overlap-range-scan pattern as maintenance logs; both feed the same `AvailabilityService::isRangeFree()` check.

**vehicle_reservation_slots** — **the single source of truth for "is this vehicle taken on this day."** Every hold and every confirmed booking inserts one row per reserved calendar day.
`id, vehicle_id FK→vehicles, slot_date date, holdable_type varchar (App\Models\BookingHold | App\Models\Booking), holdable_id bigint, created_at`
- `UNIQUE (vehicle_id, slot_date)` — **this is the double-booking guarantee itself**, not just a performance index. A concurrent insert attempt for an already-reserved (vehicle, date) pair fails at the database level with a duplicate-key error inside the same transaction that holds `lockForUpdate()` on the vehicle row — so correctness does not depend on the lock alone (the brief calls this out explicitly as the required final layer).
- `INDEX (holdable_type, holdable_id)` — releasing all slots for an expired hold, or all slots for a cancelled booking, is a single indexed delete.

**booking_holds**
`id, hold_key varchar(64) unique (idempotency key from the client), vehicle_id FK→vehicles nullable, category_id FK→vehicle_categories nullable, package_id FK→packages nullable, customer_session_id varchar(64), start_at datetime, end_at datetime, expires_at datetime, status enum(active,converted,expired,released)`
- `UNIQUE (hold_key)` — this is what makes double-submits/retries of the checkout form idempotent: a repeat POST with the same key returns the existing hold instead of creating a second one.
- `INDEX (expires_at, status)` — the every-minute scheduled `ReleaseExpiredHolds` job filters exactly on `status = 'active' AND expires_at < now()`; composite index makes it a tight range scan even with thousands of stale holds.

**bookings**
`id, reference varchar(20) unique (non-sequential, e.g. MTR-7K3Q9X), customer_id FK→customers, vehicle_id FK→vehicles, package_id FK→packages nullable, business_location_id FK→business_locations, delivery_zone_id FK→delivery_zones nullable, pickup_type enum(office,delivery), start_at datetime, end_at datetime, status enum(hold,pending_payment,confirmed,active,completed,cancelled,no_show,expired), price_breakdown json, total_amount decimal(10,2), currency_id FK→currencies, deposit_amount decimal(10,2), amount_paid decimal(10,2) default 0, locale_at_booking varchar(5), idempotency_key varchar(64) unique, created_at, updated_at`
- `UNIQUE (reference)` — public-facing lookup (customer portal, admin search, invoice), and deliberately non-sequential per the brief's IDOR-prevention requirement (generated as a random base32 string, checked for collision on insert, not derived from `id`).
- `UNIQUE (idempotency_key)` — second half of the double-submit guarantee: hold conversion to booking reuses the hold's `hold_key`.
- `INDEX (vehicle_id, status, start_at, end_at)` — exactly the composite the brief names in §9; it's the query the conflict-check and the admin Gantt calendar both run ("this vehicle's non-cancelled bookings overlapping this range").
- `INDEX (status, created_at)` — the admin bookings list default view (recent, filterable by status) and the hold-expiry/reporting jobs.
- `INDEX (customer_id)` — customer booking history.

**booking_status_history**
`id, booking_id FK→bookings, from_status varchar(20), to_status varchar(20), changed_by FK→users nullable, reason text nullable, created_at`
- `INDEX (booking_id, created_at)` — full audit trail rendered per booking, chronologically.

**booking_addons**
`id, booking_id FK→bookings, addon_id FK→addons, quantity tinyint, unit_price decimal(10,2)`
- `INDEX (booking_id)`

**booking_extra_charges**
`id, booking_id FK→bookings, type enum(damage,fuel,late_return,other), amount decimal(10,2), notes text nullable, created_by FK→users`
- `INDEX (booking_id)`

**booking_documents**
`id, customer_id FK→customers, booking_id FK→bookings nullable, type enum(passport,license,idp,permit), disk_path varchar (private disk, never public/), status enum(pending,approved,rejected), rejection_reason varchar nullable, reviewed_by FK→users nullable, reviewed_at datetime nullable`
- `INDEX (customer_id)`, `INDEX (booking_id)` — verification queue and per-booking document display.

---

## Customer

**customers**
`id, email varchar(191) unique, phone varchar(30), full_name varchar, nationality varchar(2) (ISO 3166-1 alpha-2), passport_number varchar (encrypted cast), password varchar nullable (guest checkout = null until set-password), locale_preference varchar(5), email_verified_at datetime nullable, deleted_at`
- `UNIQUE (email)` — login + the exact key guest-checkout account-matching uses ("does an account already exist for this email").

---

## Payment

**payments**
`id, booking_id FK→bookings, gateway varchar(20), gateway_reference varchar(191), type enum(deposit,balance,refund), amount decimal(10,2), currency_id FK→currencies, status enum(pending,succeeded,failed,refunded), raw_response json, created_at`
- `INDEX (booking_id, status)` — "has this booking's deposit succeeded" is checked on every webhook and on the confirmation page.
- `INDEX (gateway_reference)` — webhook handler looks up the local payment row by the gateway's own reference to avoid double-processing the same event.

**payment_webhook_logs**
`id, gateway varchar(20), event_type varchar(60), payload json, signature_verified bool, processed_at datetime nullable, created_at`
- `INDEX (gateway, event_type, created_at)` — debugging/replay queries in the admin system tools.

---

## CMS

**pages** `id, template varchar, title [T], content [T] json, is_published bool, published_at datetime nullable`
**faqs** `id, question [T], answer [T], category varchar nullable, sort_order int, is_active bool`
**testimonials** `id, customer_name varchar, country varchar(2), rating tinyint, content [T], is_approved bool, source varchar nullable, created_at`
**blog_posts** `id, title [T], excerpt [T], body [T], cover_image varchar nullable, author_id FK→users, published_at datetime nullable, is_published bool`
- `INDEX (is_published, published_at)` on **blog_posts** — the public blog list's exact filter+sort.

**reviews** `id, booking_id FK→bookings nullable, customer_name varchar, country varchar(2), rating tinyint, content text, is_approved bool, created_at`
- `INDEX (is_approved, created_at)` — public reviews display and `AggregateRating` schema generation both filter approved-only, newest first.

(`pages`, `blog_posts` slugs live in `route_slugs`, not a local column — see Localization.)

---

## SEO

**seo_meta**
`id, model_type varchar, model_id bigint, locale varchar(5), meta_title varchar(255) nullable, meta_description varchar(500) nullable, og_title varchar(255) nullable, og_description varchar(500) nullable, og_image varchar nullable, robots_index bool default true, robots_follow bool default true, canonical_override varchar nullable`
- `UNIQUE (model_type, model_id, locale)` — exactly one meta row per translatable record per locale; also the lookup key the `HasSeoMeta` trait uses.

---

## Admin / Auth / Ops

**users** (staff/admin) `id, name, email unique, password, two_factor_secret varchar nullable (encrypted), two_factor_recovery_codes text nullable (encrypted), two_factor_confirmed_at datetime nullable, locale varchar(5) default en, last_login_at datetime nullable, last_login_ip varchar(45) nullable, is_active bool default true, deleted_at`
- `UNIQUE (email)`

Plus the standard tables created by **spatie/laravel-permission** (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) and **spatie/laravel-activitylog** (`activity_log`, polymorphic `subject`/`causer`) — not hand-designed here since the packages own their migrations; documented for completeness only.

**login_attempts**
`id, email varchar nullable, ip varchar(45), successful bool, user_agent varchar nullable, created_at`
- `INDEX (email, created_at)` and `INDEX (ip, created_at)` — both are queried by the lockout middleware on every login POST ("N failed attempts for this email/IP in the last M minutes"); two separate indexes because the lockout policy checks both independently.

**blocked_ips**
`id, ip varchar(45) unique, reason varchar nullable, blocked_until datetime nullable, created_by FK→users`
- `UNIQUE (ip)`

**sessions** — Laravel's standard `database` session table (`id, user_id nullable, ip_address, user_agent, payload, last_activity`) is used as-is; `last_activity` is already indexed by Laravel's stock migration, which is sufficient for the admin "active sessions" list (filtered by recent `last_activity`).

`jobs`, `failed_jobs`, `job_batches` — Laravel's standard queue tables, used as-is (queue monitor screen reads these directly).

`notifications` — Laravel's standard polymorphic notifications table, used for in-admin alert display (new booking, failed payment, etc.); customer-facing transactional email is sent via queued `Mailable`s, not stored notifications.

**report_exports** (optional, Report module)
`id, type varchar, filters json, file_path varchar, generated_by FK→users, created_at`
- `INDEX (generated_by, created_at)` — "my recent exports" list.

---

## Cross-cutting index summary (the ones the brief calls out by name in §9)

| Index | Table | Purpose |
|---|---|---|
| `UNIQUE (vehicle_id, slot_date)` | vehicle_reservation_slots | double-booking impossibility guarantee |
| `INDEX (vehicle_id, status, start_at, end_at)` | bookings | conflict check + Gantt calendar |
| `INDEX (status, created_at)` | bookings | admin list default view |
| `UNIQUE (reference)` | bookings | non-sequential public lookup |
| `UNIQUE (email)` | customers | login / guest-account matching |
| `UNIQUE (code)` | locales, currencies, coupons | lookup + integrity |

All FK columns are indexed automatically by InnoDB; this document only lists indexes **beyond** that default.
