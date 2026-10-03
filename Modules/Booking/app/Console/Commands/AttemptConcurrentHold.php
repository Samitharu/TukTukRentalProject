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
 * int|null, "error": string|null} — the test process reads this back.
 */
final class AttemptConcurrentHold extends Command
{
    protected $signature = 'booking:attempt-concurrent-hold
        {vehicle_id : Vehicle to hold}
        {start_at : Y-m-d}
        {end_at : Y-m-d}
        {hold_key : Unique idempotency key for this attempt}';

    protected $description = 'Test-only: attempt a single hold creation, for use as a spawned concurrent process';

    public function handle(BookingService $bookings): int
    {
        try {
            $hold = $bookings->createHold([
                'hold_key' => (string) $this->argument('hold_key'),
                'start_at' => (string) $this->argument('start_at'),
                'end_at' => (string) $this->argument('end_at'),
                'vehicle_id' => (int) $this->argument('vehicle_id'),
            ]);

            $this->line(json_encode(['success' => true, 'hold_id' => $hold->id, 'error' => null]));

            return self::SUCCESS;
        } catch (NoVehicleAvailableException|Throwable $exception) {
            $this->line(json_encode(['success' => false, 'hold_id' => null, 'error' => $exception->getMessage()]));

            return self::SUCCESS;
        }
    }
}
