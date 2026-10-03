<?php

declare(strict_types=1);

namespace Modules\Booking\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Booking\Console\Commands\AttemptConcurrentHold;
use Modules\Booking\Console\Commands\ReleaseExpiredHolds;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Policies\BookingPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BookingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Booking';

    protected string $nameLower = 'booking';

    /**
     * @var string[]
     */
    protected array $commands = [
        ReleaseExpiredHolds::class,
        AttemptConcurrentHold::class,
    ];

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // A stable, explicit morph map for the vehicle_reservation_slots
        // polymorphic pointer — not the raw FQCN, so renaming/moving these
        // classes later can't silently orphan existing reservation rows.
        // Not `enforceMorphMap()`: that would require *every* morph
        // relation in the app (including spatie/laravel-permission's
        // User↔Role pivot) to be registered too.
        Relation::morphMap([
            'booking_hold' => BookingHold::class,
            'booking' => Booking::class,
        ]);

        Gate::policy(Booking::class, BookingPolicy::class);
    }

    /**
     * Brief §6 point 4: every minute, every environment — this is the
     * mechanism that actually frees an abandoned checkout's held vehicle.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(ReleaseExpiredHolds::class)->everyMinute()->withoutOverlapping();
    }
}
