<?php

declare(strict_types=1);

return [
    'name' => 'Booking',

    // Brief §6 point 4: a hold expires this many minutes after creation.
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),
];
