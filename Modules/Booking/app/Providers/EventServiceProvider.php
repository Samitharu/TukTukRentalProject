<?php

declare(strict_types=1);

namespace Modules\Booking\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        \Modules\Booking\Events\BookingPlaced::class => [
            \Modules\Booking\Listeners\SendNewBookingNotificationToAdmins::class,
            \Modules\Booking\Listeners\SendBookingConfirmationToCustomer::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void
    {
    }
}
