<?php

declare(strict_types=1);

namespace Modules\Booking\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

    private const int BATCH_SIZE = 500;

    public function handle(AvailabilityService $availability): int
    {
        $released = 0;
        $morphClass = (new BookingHold())->getMorphClass();

        // Batches of ids (chunkById): constant memory however large a
        // backlog has built up (e.g. after the scheduler was down), and 3
        // queries + one commit per batch rather than per hold — releasing
        // 8k holds one transaction at a time took >2 minutes.
        //
        // Each batch re-selects its holds under a row lock, so one that
        // confirmHold() converted in the meantime is left alone, and its
        // slots and status always change together.
        BookingHold::query()->expiredAndActive()->select('id')->chunkById(self::BATCH_SIZE, function ($batch) use ($availability, $morphClass, &$released): void {
            DB::transaction(function () use ($batch, $availability, $morphClass, &$released): void {
                $ids = BookingHold::query()
                    ->whereKey($batch->pluck('id'))
                    ->expiredAndActive()
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                if ($ids === []) {
                    return;
                }

                $availability->releaseSlotsForHoldables($morphClass, $ids);
                BookingHold::query()->whereKey($ids)->update(['status' => BookingHold::STATUS_EXPIRED, 'updated_at' => now()]);
                $released += count($ids);
            });
        });

        $this->info("Released {$released} expired hold(s).");

        return self::SUCCESS;
    }
}
