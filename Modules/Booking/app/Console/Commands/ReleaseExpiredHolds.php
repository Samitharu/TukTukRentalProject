<?php

declare(strict_types=1);

namespace Modules\Booking\Console\Commands;

use Illuminate\Console\Command;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Models\BookingHold;

/**
 * Brief §6 point 4: expired holds are released by a scheduled job every
 * minute (registered in BookingServiceProvider::configureSchedules()) and
 * also ignored lazily by availability queries in the meantime (an expired-
 * but-not-yet-swept hold's slots still physically occupy
 * vehicle_reservation_slots until this runs — deliberately conservative:
 * better to briefly under-sell availability than to double-book while a
 * cron is running late).
 */
final class ReleaseExpiredHolds extends Command
{
    protected $signature = 'booking:release-expired-holds';

    protected $description = 'Release booking holds past their expiry and free their reserved slots';

    public function handle(AvailabilityService $availability): int
    {
        $expired = BookingHold::query()->expiredAndActive()->get();

        foreach ($expired as $hold) {
            $availability->releaseSlots($hold);
            $hold->update(['status' => BookingHold::STATUS_EXPIRED]);
        }

        $this->info("Released {$expired->count()} expired hold(s).");

        return self::SUCCESS;
    }
}
