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

    // Who receives the "new booking placed" email (comma-separated). Falls
    // back to the business contact address when unset or left empty.
    'admin_notification_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) (env('BOOKING_ADMIN_NOTIFICATION_EMAILS') ?: env('BUSINESS_EMAIL', 'hello@mirandatuktuk.example'))),
    ))),

    // Queue the admin email is pushed onto — keep it off the default queue
    // if you run a dedicated worker for mail (`queue:work --queue=mail,default`).
    'notification_queue' => env('BOOKING_NOTIFICATION_QUEUE', 'default'),
];
