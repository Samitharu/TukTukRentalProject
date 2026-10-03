# Phase 3 Summary — Fleet + Package Builder + Pricing + Availability Engine

Status: **complete, awaiting your confirmation before Phase 4** (Booking engine with full conflict prevention + holds + state machine).

## What was built this phase

Four new modules, each with real migrations, models, services, admin CRUD, and — for the two "engines" the brief calls out explicitly — a full Pest test suite. Verified against both an in-memory SQLite test run and a live MySQL database via the running dev server.

### Fleet module
- Schema: `vehicle_categories`, `vehicles`, `vehicle_images`, `vehicle_maintenance_logs` — exactly as designed in [03-database-schema.md](03-database-schema.md).
- `Vehicle` / `VehicleCategory` translatable (name/description) into all active locales.
- `VehicleImageService`: uploads are auto-resized (max 1600px) and converted to WebP via `intervention/image` (GD driver, EXIF stripped), plus a separate 400px thumbnail — pure PHP, no ImageMagick dependency, matching the shared-hosting constraint.
- Admin CRUD: categories, vehicles (with multi-photo upload/delete), per-vehicle maintenance log.
- Permissions: `fleet.view`, `fleet.manage`.

### Package module
- Schema: `packages`, `package_vehicles`, `package_categories`, `package_pricing_tiers`, `package_seasons`, `addons`, `package_addons`.
- **`PackagePricingTierValidator`**: enforces "no gaps, no overlaps, at most one open-ended tier" across a package's pricing brackets — the brief's §5 "pricing consistency" requirement, unit-tested.
- **`PackageSeasonValidator`**: enforces no seasonal-date-range collisions, correctly allowing two seasons to cover the *same* dates when their weekday masks don't intersect (e.g. a weekday rate and a weekend rate) — unit-tested.
- Admin package builder: basic fields, pricing tiers, seasonal overrides (with a weekday picker), category/vehicle restrictions, add-on association (offered vs. included-free) — all on one edit screen, each section its own small form/validate/save cycle (no JS framework yet; Alpine.js-driven single-page editing is a Phase 5 design-system concern, not a Phase 3 one).
- Separate top-level Add-ons CRUD (helmet, phone holder, SIM card, etc., admin-defined per the brief, not hardcoded).
- Permissions: `packages.view`, `packages.manage`, `addons.manage`.

### Pricing module
- Schema: `currencies`, `exchange_rates`, `coupons`.
- **`PricingService`** — the single authoritative price calculator every future caller (booking checkout in Phase 4, the public price-recalculation endpoint in Phase 5) will use. Brief §7: *"prices always recalculated server-side; never trust client totals."* Computes, in order: base amount (tier/day-count-aware, with distinct handling for per-day/week/month/tiered vs. fixed-bundle pricing models) → seasonal adjustment (weekday-aware) → add-ons (flat vs. per-day, quantity-capped, restricted to what the package actually offers) → delivery fee → coupon discount (validity/usage/min-days checked) → deposit (percentage or fixed, capped at total) → currency conversion. Fully unit-tested (9 cases covering every branch above).
- Admin CRUD: coupons, currencies + manual exchange-rate entry. (Brief's "auto-fetched daily by a scheduled job" is a scheduler concern for a later phase — manual entry is the correct MVP given there's no scheduled-job infrastructure yet.)
- Permission: `pricing.manage`.

### Availability module
- Schema: `business_locations`, `delivery_zones`, `availability_blackouts`, and — per its documented ownership in [03-database-schema.md](03-database-schema.md) — **`vehicle_reservation_slots`**, the table whose `UNIQUE(vehicle_id, slot_date)` constraint is the actual double-booking guarantee described in the brief's §6. Only its *read* side is exercised this phase (nothing writes real holds/bookings into it yet — that's Phase 4); the table and its query logic exist now specifically so Phase 4 has no schema migration of its own to add.
- **`AvailabilityService`** — `isRangeFree()` checks blackouts, maintenance logs (from Fleet), and reservation slots, with a configurable buffer window between bookings; `availableVehiclesInCategory()` (the pool Phase 4's automatic-vehicle-assignment will pick from); `meetsMinimumNotice()` / `withinAdvanceWindow()` for the brief's "minimum notice" / "maximum advance booking" settings. Fully unit-tested (12 cases, including buffer-day edge cases).
- Admin CRUD: business locations (pickup points), blackout dates (per-vehicle or business-wide).
- Permissions: `availability.view`, `availability.manage`.

## Two real bugs found and fixed this phase

1. **Eloquent's `date` cast is not cross-database-safe for range queries.** A bare `'column' => 'date'` cast still serializes through the connection's full datetime format on write. MySQL's `DATE` column type silently truncates that to just the date — but SQLite (used by the test suite) stores the value verbatim, including `00:00:00`. That broke `whereBetween('slot_date', [...])`-style queries in tests: a string like `"2026-06-05 00:00:00"` sorts *after* the plain string `"2026-06-05"`, so a slot on the exact boundary date silently fell outside the queried range. Fixed by using explicit `'date:Y-m-d'` casts on every date-only column across the four new modules, which the `AvailabilityServiceTest` buffer-day test now guards against regressing. Worth knowing for any future date-only column.
2. **Laravel's default factory-name resolution doesn't know about the `Modules/*` convention.** It assumes `App\Models\X` → `Database\Factories\XFactory`; our models live at `Modules\{Module}\Models\X` with factories at `Modules\{Module}\Database\Factories\XFactory`. Fixed once, globally, via `Factory::guessFactoryNamesUsing()` in `app/Providers/AppServiceProvider.php` — every module's `HasFactory` trait now resolves correctly without each model needing its own `newFactory()` override.

## Assumptions made this phase

| Item | Assumption | Reasoning |
|---|---|---|
| Tiered-pricing semantics | A pricing tier's `price` is a **per-day rate** applied across the whole stay (e.g. a 5-day rental in the "4-7 day" bracket = that bracket's daily rate × 5); `fixed_bundle` is the one model where the tier's price is a **flat total**, not multiplied. | The original schema doc left this ambiguous; a per-day-rate-by-bracket is the standard car/tuk-tuk-rental pattern ("shorter rentals cost more per day") and matches the brief's own example ("1-3 days, 4-7 days, 8+ days" reads naturally as day-count brackets with their own daily rate). Flag if you intended flat bundle pricing per tier instead — it's a small, isolated change in `PricingService::calculateBaseAmount()`. |
| Vehicle "features" | A fixed checkbox list (helmet, phone holder, GPS, Bluetooth speaker, storage box, sun canopy), not admin-extensible | These are vehicle *attributes* (what this particular tuk tuk has), distinct from Package add-ons (what a customer can *purchase* — helmet, SIM card, insurance upgrade, etc. — which **are** fully admin-defined via the Add-ons CRUD, per the brief). A fixed small list keeps the vehicle form simple; extending it to an admin-managed list is a small follow-up if wanted. |
| Exchange rates | Manually entered by an admin (one click per currency), not auto-fetched | Brief allows either "manually set or auto-fetched daily by a scheduled job." Auto-fetching needs the scheduler/queue infrastructure that's a Phase 7/9 concern; manual entry is fully functional now and the schema (`exchange_rates` history table) already supports switching to a scheduled job later with zero migration changes. |
| Package builder UX | Multiple small forms (one per tier/season/add-on action), full page reload each time | Matches the brief's own phase separation — the JS-driven, no-reload editing experience is explicitly a Phase 5 "design system" concern (Alpine.js gets vendored then). The underlying validation/business logic (what Phase 3 is actually about) is complete and tested regardless of how the form posts. |

## Next step

On your go-ahead, Phase 4 begins: **the Booking engine** — holds, the state machine (`hold → pending_payment → confirmed → active → completed`, plus `cancelled`/`no_show`/`expired`), and the concurrency-safe conflict-prevention mechanism the brief calls out as critical (§6): transactional slot reservation against `vehicle_reservation_slots` (already schema-ready from this phase), idempotency keys, expired-hold cleanup, and the concurrency tests proving two simultaneous booking attempts for the same vehicle/dates produce exactly one success.
