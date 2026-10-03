# ER Diagram

Scope note: this diagram covers the **Fleet → Package → Pricing → Availability → Booking → Payment → Customer** core — the part of the schema with real relational complexity and the double-booking-critical tables. CMS, SEO, Admin/Auth, and Report tables are simple (mostly polymorphic-to-anything or standalone lookup tables) and are described in prose in [03-database-schema.md](03-database-schema.md) rather than drawn, to keep this diagram legible.

```mermaid
erDiagram
    VEHICLE_CATEGORIES ||--o{ VEHICLES : "categorizes"
    VEHICLES ||--o{ VEHICLE_IMAGES : "has"
    VEHICLES ||--o{ VEHICLE_MAINTENANCE_LOGS : "has"
    VEHICLES ||--o{ AVAILABILITY_BLACKOUTS : "blocked by"
    VEHICLES ||--o{ VEHICLE_RESERVATION_SLOTS : "reserved via"
    VEHICLES ||--o{ BOOKINGS : "assigned to"
    VEHICLES ||--o{ BOOKING_HOLDS : "held for"

    PACKAGES ||--o{ PACKAGE_PRICING_TIERS : "priced by"
    PACKAGES ||--o{ PACKAGE_SEASONS : "seasonal overrides"
    PACKAGES }o--o{ PACKAGE_ADDONS : "includes"
    ADDONS ||--o{ PACKAGE_ADDONS : "offered in"
    PACKAGES }o--o{ PACKAGE_VEHICLES : "restricts to"
    VEHICLES }o--o{ PACKAGE_VEHICLES : "eligible for"
    PACKAGES }o--o{ PACKAGE_CATEGORIES : "restricts to"
    VEHICLE_CATEGORIES }o--o{ PACKAGE_CATEGORIES : "eligible for"
    PACKAGES ||--o{ BOOKINGS : "booked as"
    PACKAGES ||--o{ BOOKING_HOLDS : "held as"

    CURRENCIES ||--o{ EXCHANGE_RATES : "rated over time"
    CURRENCIES ||--o{ BOOKINGS : "priced in"
    CURRENCIES ||--o{ PAYMENTS : "charged in"

    BOOKING_HOLDS ||--o{ VEHICLE_RESERVATION_SLOTS : "reserves (via holdable)"
    BOOKINGS ||--o{ VEHICLE_RESERVATION_SLOTS : "reserves (via holdable)"
    BOOKING_HOLDS |o--|| BOOKINGS : "converts to"

    CUSTOMERS ||--o{ BOOKINGS : "makes"
    CUSTOMERS ||--o{ BOOKING_DOCUMENTS : "uploads"
    BOOKINGS ||--o{ BOOKING_DOCUMENTS : "attached to"
    BOOKINGS ||--o{ BOOKING_ADDONS : "includes"
    ADDONS ||--o{ BOOKING_ADDONS : "sold in"
    BOOKINGS ||--o{ BOOKING_EXTRA_CHARGES : "incurs"
    BOOKINGS ||--o{ BOOKING_STATUS_HISTORY : "audited by"
    BOOKINGS ||--o{ PAYMENTS : "paid via"

    BUSINESS_LOCATIONS ||--o{ BOOKINGS : "pickup point"
    DELIVERY_ZONES ||--o{ BOOKINGS : "delivered to"
    BUSINESS_LOCATIONS ||--o{ VEHICLES : "based at"

    VEHICLES {
        bigint id PK
        bigint category_id FK
        json name
        varchar plate_no UK
        enum status
    }
    PACKAGES {
        bigint id PK
        json name
        enum pricing_model
        smallint min_days
        smallint max_days
    }
    VEHICLE_RESERVATION_SLOTS {
        bigint id PK
        bigint vehicle_id FK
        date slot_date
        varchar holdable_type
        bigint holdable_id
    }
    BOOKING_HOLDS {
        bigint id PK
        varchar hold_key UK
        bigint vehicle_id FK
        bigint package_id FK
        datetime expires_at
        enum status
    }
    BOOKINGS {
        bigint id PK
        varchar reference UK
        bigint customer_id FK
        bigint vehicle_id FK
        bigint package_id FK
        bigint business_location_id FK
        bigint delivery_zone_id FK
        bigint currency_id FK
        datetime start_at
        datetime end_at
        enum status
        varchar idempotency_key UK
    }
    CUSTOMERS {
        bigint id PK
        varchar email UK
        varchar passport_number
    }
    PAYMENTS {
        bigint id PK
        bigint booking_id FK
        varchar gateway
        enum type
        enum status
    }
```

### Reading the diagram

- `VEHICLE_RESERVATION_SLOTS.holdable_type/holdable_id` is a polymorphic pointer to either `BOOKING_HOLDS` or `BOOKINGS` (Laravel `morphTo`) — drawn here as two relationships out of the slots table rather than a true polymorphic notation, since Mermaid `erDiagram` has no native polymorphic-FK syntax. The `UNIQUE (vehicle_id, slot_date)` constraint on this table (not expressible in Mermaid's ER syntax either) is the actual double-booking guarantee — see [03-database-schema.md](03-database-schema.md).
- `BOOKING_HOLDS |o--|| BOOKINGS` ("converts to") is not a foreign key — it's the application-level relationship where `BookingService::confirmHold()` creates a `Booking` reusing the hold's `hold_key` as the booking's `idempotency_key`, then marks the hold `converted`. Modeling it as an FK would force every hold to eventually have a booking, which isn't true (holds also expire or get released).
