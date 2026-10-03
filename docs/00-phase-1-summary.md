# Phase 1 Summary — Architecture & Data Design

Status: **complete, awaiting your confirmation before Phase 2** (per the brief's "stop after each phase" instruction).

## What was built this phase

Planning/design deliverables only — no application code yet, as Phase 1 in the brief's own phase list is architecture, not implementation:

1. [01-architecture.md](01-architecture.md) — stack summary, request lifecycle, module anatomy convention (every `nwidart/laravel-modules` module follows the same internal skeleton), locale-routing design (translated slugs resolved via a `route_slugs` table, not hardcoded routes — new languages need zero route-file changes), the booking-conflict architecture summary, and every assumption made where the brief left a placeholder.
2. [02-modules.md](02-modules.md) — the 14-module list with dependency order and a specific justification for each module's existence (why it isn't folded into a neighbor).
3. [03-database-schema.md](03-database-schema.md) — full table-by-table schema (~40 tables) covering every module, with every index beyond automatic FK indexes documented with a one-line reason it exists — including the composite indexes the brief names explicitly in §9 and the `UNIQUE (vehicle_id, slot_date)` constraint that is the actual double-booking guarantee, not just a performance aid.
4. [04-er-diagram.md](04-er-diagram.md) — Mermaid ER diagram of the core Fleet→Package→Pricing→Availability→Booking→Payment→Customer relational graph (CMS/SEO/Admin tables documented in prose instead, to keep the diagram legible).
5. [05-packages.md](05-packages.md) — every Composer package the architecture currently calls for, justified individually, plus a "considered and rejected" section (e.g. Horizon rejected because it hard-requires Redis, which the brief only allows as optional).

## Key design decisions worth flagging

- **Conflict prevention is schema-first, not just service-first.** The `vehicle_reservation_slots` table with `UNIQUE (vehicle_id, slot_date)` is the actual source of truth the brief demands in §6 point 1 — application-level locking (`lockForUpdate()`) reduces contention, but correctness doesn't depend on it alone.
- **Holds and bookings share one reservation path.** Both write through the same `AvailabilityService::reserveSlots()` method, which is also what guarantees admin edits can't bypass the same checks a customer booking goes through (§6 point 8).
- **Translated slugs live in one polymorphic table** (`route_slugs`) rather than per-model slug columns, which is what makes "add a language from the admin, no code changes" actually true for routing, not just for UI strings.
- **Payment gateway interface built now, Stripe wired first, PayHere stubbed** — since the brief left the gateway choice blank; this doesn't block later phases and doesn't need to be revisited unless you want PayHere to be the primary/only gateway instead.

## Assumptions requiring your confirmation (also listed in 01-architecture.md §7)

| Item | Assumption made | Needed by |
|---|---|---|
| Business address/phone/email | left blank, placeholders only | content seeding (Phase 9), before go-live |
| Base currency | USD | Phase 6 (Payment), Phase 3 (Pricing) |
| Payment gateway | Stripe first, PayHere interface-ready stub | Phase 6 |
| Online payment | **Confirmed off at launch** (startup, pay-on-pickup only). Built in full but gated behind `payment.online_enabled` setting, default false — flip on later with no code change. | Phase 6 |
| Deposit rule | 30% online / balance on pickup, but moot while online payment is off (deposit forced to 0) | Phase 6, once enabled |
| Hold expiry | 15 minutes, admin-configurable | Phase 4 |
| Admin panel path | `/control-panel` | Phase 2 |
| Brand colours/logo | none provided — a professional tropical-but-trustworthy palette will be proposed as CSS tokens in Phase 5 unless you provide one first | Phase 5 |

None of these block Phase 2 (Core + Localization + auth + admin layout + roles/permissions + security middleware) — they only matter starting Phase 5/6. Feel free to answer them now or later.

## Next step

On your go-ahead, Phase 2 begins: scaffold the actual Laravel 12 project, install the Composer packages from [05-packages.md](05-packages.md), set up `nwidart/laravel-modules`, and build the Core + Localization modules, base admin layout, roles/permissions, and the security-headers/rate-limiting middleware — with real, runnable code (no placeholders), per the brief's "never leave TODOs or pseudo-code" rule.
