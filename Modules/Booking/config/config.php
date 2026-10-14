<?php

declare(strict_types=1);

return [
    'name' => 'Booking',

    // Brief §6 point 4: a hold expires this many minutes after creation.
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),

    // Longest single rental the public flow accepts. Besides being a sane
    // business limit, it caps how many vehicle_reservation_slots rows (one
    // per day) a single request can make the database write.
    'max_rental_days' => (int) env('BOOKING_MAX_RENTAL_DAYS', 180),

    // Hourly tuk tuk rentals: the earliest hour a rental may start and the
    // hour it must be back by (24h clock, whole hours). The tuk tuk is
    // reserved for the whole day either way.
    'hourly' => [
        'first_start_hour' => (int) env('BOOKING_HOURLY_FIRST_START', 6),
        'last_return_hour' => (int) env('BOOKING_HOURLY_LAST_RETURN', 22),
    ],

    // Who receives the "new booking placed" email (comma-separated). Falls
    // back to the business contact address when unset or left empty.
    'admin_notification_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) (env('BOOKING_ADMIN_NOTIFICATION_EMAILS') ?: env('BUSINESS_EMAIL', 'hello@mirandatuktuk.example'))),
    ))),

    // Queue the admin email is pushed onto — keep it off the default queue
    // if you run a dedicated worker for mail (`queue:work --queue=mail,default`).
    'notification_queue' => env('BOOKING_NOTIFICATION_QUEUE', 'default'),

    // Spam-booking detection (admin only — nothing is blocked automatically).
    // A booking is flagged once this many upcoming, not-yet-picked-up
    // bookings share a customer, IP, phone or passport. Kept above 2 so a
    // traveller who books two legs of their trip isn't flagged.
    'suspicious_threshold' => max(2, (int) env('BOOKING_SUSPICIOUS_THRESHOLD', 3)),

    // How long "Block IP" on a booking keeps that IP from booking online.
    // Not forever: mobile networks share IPs, so a later real tourist could
    // get the same one.
    'block_ip_days' => (int) env('BOOKING_BLOCK_IP_DAYS', 30),
];
