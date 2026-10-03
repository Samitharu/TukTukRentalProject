<?php

declare(strict_types=1);

return [
    'name' => 'Availability',

    // Calendar days required between one booking's return and the next
    // booking's pickup for the same vehicle (cleaning/inspection). Default 0:
    // same-day turnover is normal for a small tuk tuk fleet; raise it if the
    // business wants a mandatory gap.
    'buffer_days' => (int) env('AVAILABILITY_BUFFER_DAYS', 0),

    // A booking's pickup must be at least this many hours from now.
    'minimum_notice_hours' => (int) env('AVAILABILITY_MIN_NOTICE_HOURS', 4),

    // A booking's pickup cannot be more than this many days in the future.
    'maximum_advance_days' => (int) env('AVAILABILITY_MAX_ADVANCE_DAYS', 365),

    // Simultaneous pickups the business can physically hand over at once
    // (staff capacity), independent of vehicle availability. Enforced by
    // BookingService in Phase 4, not by AvailabilityService — kept here
    // since it's an Availability *setting*, not a Booking *mechanism*.
    'daily_pickup_capacity' => (int) env('AVAILABILITY_DAILY_PICKUP_CAPACITY', 10),
];
