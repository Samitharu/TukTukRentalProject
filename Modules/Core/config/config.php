<?php

declare(strict_types=1);

return [
    'name' => 'Core',

    // Business contact details. Placeholders until the business owner
    // supplies real values (see docs/01-architecture.md §7) — becomes
    // admin-editable via the Settings screen in Phase 7; .env-driven for
    // now so it's one place to change ahead of that.
    'business' => [
        'phone' => env('BUSINESS_PHONE', '+94 00 000 0000'),
        'whatsapp' => env('BUSINESS_WHATSAPP', '94000000000'), // digits only, no + or spaces
        'email' => env('BUSINESS_EMAIL', 'hello@mirandatuktuk.example'),
        'address' => env('BUSINESS_ADDRESS', '[Address pending — see docs/01-architecture.md §7]'),
        // Short line shown under the brand name in the header logo mark
        // (e.g. "Ride Sri Lanka") — optional, blank by default.
        'tagline' => env('BUSINESS_TAGLINE', ''),
    ],
];
