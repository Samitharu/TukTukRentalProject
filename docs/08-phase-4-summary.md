# Phase 4 Summary — Booking Engine: Conflict Prevention, Holds, State Machine

Status: **complete, awaiting your confirmation before Phase 5** (Public website: design system, all pages, booking flow, responsive QA).

This is the brief's own "CRITICAL — ZERO DOUBLE BOOKINGS" section (§6), and it was treated with the rigor that phrase demands: real concurrent-process testing against the live database, not just unit tests asserting the mechanism exists.

## What was built this phase

### Customer module (minimal, by design)
A `customers` table/model and `CustomerService::findOrCreateGuest()` — guest checkout (brief §4: "no account required up front") matched or created by email. Admin gets a read-only customer directory (search + booking history) since the brief's booking-management screens need it for "search by reference/name/email." The full customer-facing account/login/portal page is explicitly a Phase 5 deliverable ("Customer Account" is listed under the public website's pages) — building it now would mean building public-facing UI before the design system it should use exists.

### Booking module — the engine
- Schema: `booking_holds`, `bookings`, `booking_status_history`, `booking_addons`, `booking_extra_charges`, plus the `reserveSlots`/`releaseSlots`/`repointSlots`/`lockVehicle` write-path added to **Availability's** `AvailabilityService` (its `vehicle_reservation_slots` table, from Phase 3, was schema-ready for exactly this).
- **`BookingReference`**: non-sequential `MTR-XXXXXXXX` references (brief §8 IDOR prevention), collision-checked, ambiguous characters (0/O, 1/I) excluded.
- **`BookingStateMachine`**: the exact lifecycle from brief §5 — `hold → pending_payment → confirmed → active → completed`, plus `cancelled`/`no_show`/`expired` — as an explicit transition table, not scattered `if` statements; an invalid transition throws rather than silently succeeding.
- **`BookingService`** — the single surface for every booking write (brief §6 point 8: "Admin edits use the exact same service — no bypass"). Every method that touches availability follows the same pattern: `DB::transaction()` → `AvailabilityService::lockVehicle()` (row lock) → re-check `isRangeFree()` inside the lock → `reserveSlots()` (which surfaces the database's own `UNIQUE(vehicle_id, slot_date)` violation as a typed `SlotConflictException`, wrapped here as `NoVehicleAvailableException`). Covers: `createHold` (idempotent via `hold_key`, auto-assigns a vehicle from a category/package when none is specified), `confirmHold` (idempotent via the same key reused as the booking's `idempotency_key`; re-points — never re-inserts — the hold's slot rows so there's never a moment with no reservation), `cancel`, `changeDates`, `reassignVehicle`, `markActive`/`markCompleted`/`markNoShow`.
- **`booking:release-expired-holds`** — scheduled every minute (brief §6 point 4), frees an abandoned checkout's slots.
- Admin: booking list (filter by status/search), manual "walk-in" booking creation (goes through `BookingService` identically to what the public flow will use in Phase 5), booking detail with status actions, date-change and vehicle-reassignment forms, full status-history audit trail.
- Permissions: `bookings.view`, `bookings.manage`, `customers.view`.

## The concurrency test — and what it actually proves

The brief asks for a test that "simulates parallel requests" and proves "exactly one success." A same-process test can't genuinely prove that — PHP is single-threaded, and this environment has no `pcntl` (Windows). So `Modules\Booking\tests\Feature\ConcurrentHoldTest` spawns **two real, independent OS processes** via `proc_open`, started back-to-back before either is awaited, each running a dedicated `booking:attempt-concurrent-hold` artisan command against the **live MySQL database** (not the sqlite `:memory:` this test file itself runs under — that database exists only inside this one PHP process and isn't shared with a spawned child). It asserts exactly one process succeeds, the other fails with a conflict, and exactly one vehicle's worth of `vehicle_reservation_slots` rows exist afterward, owned by the winner. This is as close to the brief's literal request as is achievable on this OS without a multi-process test runner, and it genuinely exercises the `UNIQUE` constraint under real concurrent load rather than just asserting the constraint is present in a migration file.

## Three real bugs found — two by testing, one by live verification

1. **Eloquent's `date` cast is not the same as "MySQL DATE column" (carried over from Phase 3, listed here because it's the same class of bug caught the same way).**
2. **Carbon 3.x's `diffInDays()` returns `float`, not `int`.** `BookingController::store()` passed the raw diff straight into `PricingService::calculate()`'s strictly-typed `int $days` parameter — worked in every unit test (which pass pre-computed ints) but threw a live `TypeError` the moment a real HTTP date range hit it. Only caught by manually exercising the admin UI end-to-end, which is exactly why this session always does that in addition to the automated suite.
3. **HTTP form values are strings; `BookingService::createHold()`'s internal calls are strictly typed `int`.** `vehicle_id` arrived as `"1"` from the admin form and blew up inside `AvailabilityService::lockVehicle(int $vehicleId)`. Fixed by normalizing once at the top of `createHold()` rather than trusting every caller to pre-cast — and a new `AdminManualBookingTest` now submits real HTTP requests (not pre-typed PHP arrays) specifically to keep this class of bug caught automatically going forward, rather than requiring another round of manual click-testing to notice it again.

## Assumptions made this phase

| Item | Assumption | Reasoning |
|---|---|---|
| Reservation granularity | Every calendar day in `[pickup_date, return_date]` is reserved (inclusive) | The brief mentions per-vehicle buffer time as a *separate*, admin-configurable concept (already built in Phase 3) — so the booking's own date range doesn't need same-day-turnover logic baked in; a 0-day buffer (the default) already permits same-day pickup-after-return. |
| Currency on a booking | Snapshotted as a `currency_code` string column, not an `currency_id` FK | `price_breakdown` is already a point-in-time JSON snapshot of converted amounts; a currency code alongside it is self-contained and needs no join to redisplay an old invoice, even if that currency is later deactivated or its rate changes. |
| Booking status on confirm | Goes straight to `confirmed` (skipping `pending_payment`) while `payment.online_enabled` is false, matching the Phase 1/2 decision that this is a pay-on-pickup business for now | Already documented; Phase 6 flips this by checking the same config flag the state machine already branches on — no code restructuring needed. |
| Customer module scope | Data layer + guest-matching service + admin read-only directory only; no public login/portal | That UI belongs to the design system Phase 5 builds; building it earlier would mean unstyled, throwaway public-facing screens. |

## Next step

On your go-ahead, Phase 5 begins: the **public website** — design system (hand-written CSS with design tokens, Alpine.js vendored in), every customer-facing page, and the full multi-step booking flow (the public-facing counterpart to the engine built this phase), with responsive QA across breakpoints.
