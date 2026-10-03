<?php

declare(strict_types=1);

return [
    'name' => 'Pricing',

    'default_currency' => env('DEFAULT_CURRENCY', 'USD'),

    // See docs/01-architecture.md §7: online payment is off at launch — this
    // is a new business taking pay-on-pickup only for now. PricingService
    // forces every deposit to 0 while this is false, regardless of package
    // configuration, so the checkout flow never shows a deposit due online.
    'online_payment_enabled' => filter_var(env('PAYMENT_ONLINE_ENABLED', false), FILTER_VALIDATE_BOOL),
];
