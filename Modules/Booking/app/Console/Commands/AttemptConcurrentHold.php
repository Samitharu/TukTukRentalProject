<?php

declare(strict_types=1);

namespace Modules\Booking\Console\Commands;

use Illuminate\Console\Command;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Services\BookingService;
use Throwable;

/**
 * Test-only helper, invoked as a genuinely separate OS process by
 * Modules\Booking\tests\Feature\ConcurrentHoldTest — there is no other way
 * to prove true concurrent-request safety on Windows (no pcntl) against a
 * database that (unlike SQLite :memory:) can actually be shared across
 * processes. Never invoked by the application itself.
 *
 * Prints exactly one line of JSON to stdout: {"success": bool, "hold_id":
 * int|null, "vehicle_id": int|null, "error": string|null, "error_class":
 * string|null} — the test process reads this back.
 *
 * vehicle_id 0 + --package takes the auto-assign path (the public flow
 * when the customer didn't pick a specific tuk tuk). --start-at is a
 * shared unix timestamp every spawned process sleeps until, so a whole
 * batch hits the database at the same instant regardless of how long each
 * process took to boot.
 */
final class AttemptConcurrentHold extends Command
{
    protected $signature = 'booking:attempt-concurrent-hold
        {vehicle_id : Vehicle to hold (0 = auto-assign from --package)}
        {start_at : Y-m-d}
        {end_at : Y-m-d}
        {hold_key : Unique idempotency key for this attempt}
        {--package= : Package to auto-assign an eligible vehicle from}
        {--start-at= : Unix timestamp (float) to wait for before attempting}';

    protected $description = 'Test-only: attempt a single hold creation, for use as a spawned concurrent process';

    public function handle(BookingService $bookings): int
    {
        $startAt = (float) $this->option('start-at');

        if ($startAt > 0 && ($wait = $startAt - microtime(true)) > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        $vehicleId = (int) $this->argument('vehicle_id');

        try {
            $hold = $bookings->createHold([
                'hold_key' => (string) $this->argument('hold_key'),
                'start_at' => (string) $this->argument('start_at'),
                'end_at' => (string) $this->argument('end_at'),
                'vehicle_id' => $vehicleId > 0 ? $vehicleId : null,
                'package_id' => $this->option('package') !== null ? (int) $this->option('package') : null,
            ]);

            $this->line(json_encode(['success' => true, 'hold_id' => $hold->id, 'vehicle_id' => $hold->vehicle_id, 'error' => null, 'error_class' => null]));

            return self::SUCCESS;
        } catch (NoVehicleAvailableException|Throwable $exception) {
            $this->line(json_encode(['success' => false, 'hold_id' => null, 'vehicle_id' => null, 'error' => $exception->getMessage(), 'error_class' => $exception::class]));

            return self::SUCCESS;
        }
    }
}
