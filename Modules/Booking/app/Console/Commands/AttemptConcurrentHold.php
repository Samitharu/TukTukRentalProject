<?php

declare(strict_types=1);

namespace Modules\Booking\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Services\BookingService;
use Modules\Package\Models\Package;
use Modules\Pricing\DataObjects\PriceBreakdown;
use Modules\Pricing\Services\PricingService;
use Throwable;

/**
 * Test-only helper, invoked as a genuinely separate OS process by
 * Modules\Booking\tests\Feature\ConcurrentHoldTest — there is no other way
 * to prove true concurrent-request safety on Windows (no pcntl) against a
 * database that (unlike SQLite :memory:) can actually be shared across
 * processes. Never invoked by the application itself.
 *
 * Prints exactly one line of JSON to stdout: {"success": bool, "hold_id":
 * int|null, "vehicle_id": int|null, "booking_id": int|null, "error":
 * string|null, "error_class": string|null} — the test process reads this back.
 *
 * vehicle_id 0 + --package takes the auto-assign path (the public flow
 * when the customer didn't pick a specific tuk tuk). --start-at is a
 * shared unix timestamp every spawned process sleeps until, so a whole
 * batch hits the database at the same instant regardless of how long each
 * process took to boot. --confirm runs the whole checkout instead of just
 * the hold (hold → booking → guest customer, exactly as the public
 * flow's confirm step does); start_at/end_at may then carry times, as an
 * hourly rental's do.
 */
final class AttemptConcurrentHold extends Command
{
    protected $signature = 'booking:attempt-concurrent-hold
        {vehicle_id : Vehicle to hold (0 = auto-assign from --package)}
        {start_at : Y-m-d, or "Y-m-d H:i" for an hourly rental}
        {end_at : Y-m-d, or "Y-m-d H:i" for an hourly rental}
        {hold_key : Unique idempotency key for this attempt}
        {--package= : Package to auto-assign an eligible vehicle from}
        {--start-at= : Unix timestamp (float) to wait for before attempting}
        {--confirm : Complete the whole checkout, not just the hold}
        {--email= : Customer email for --confirm}
        {--hours= : Hours booked, for an hourly package with --confirm}';

    protected $description = 'Test-only: attempt a single hold creation, for use as a spawned concurrent process';

    public function handle(BookingService $bookings, PricingService $pricing): int
    {
        $startAt = (float) $this->option('start-at');

        if ($startAt > 0 && ($wait = $startAt - microtime(true)) > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        $vehicleId = (int) $this->argument('vehicle_id');
        $holdData = [
            'hold_key' => (string) $this->argument('hold_key'),
            'start_at' => (string) $this->argument('start_at'),
            'end_at' => (string) $this->argument('end_at'),
            'vehicle_id' => $vehicleId > 0 ? $vehicleId : null,
            'package_id' => $this->option('package') !== null ? (int) $this->option('package') : null,
        ];

        try {
            if ($this->option('confirm')) {
                $booking = $bookings->createManualBooking(
                    holdData: $holdData,
                    customerData: [
                        'email' => (string) $this->option('email'),
                        'full_name' => 'Race Customer',
                        'pickup_type' => 'office',
                    ],
                    price: $this->price($pricing, $holdData),
                );

                $this->report(true, null, $booking->vehicle_id, $booking->id);
            } else {
                $hold = $bookings->createHold($holdData);

                $this->report(true, $hold->id, $hold->vehicle_id, null);
            }

            return self::SUCCESS;
        } catch (NoVehicleAvailableException|Throwable $exception) {
            $this->report(false, null, null, null, $exception);

            return self::SUCCESS;
        }
    }

    private function price(PricingService $pricing, array $holdData): PriceBreakdown
    {
        $package = Package::query()->findOrFail($holdData['package_id']);
        $start = CarbonImmutable::parse($holdData['start_at']);
        $days = (int) $start->startOfDay()->diffInDays(CarbonImmutable::parse($holdData['end_at'])->startOfDay()) + 1;
        $hours = $this->option('hours') !== null ? (int) $this->option('hours') : null;

        return $pricing->calculate($package, $start, $days, hours: $hours);
    }

    private function report(bool $success, ?int $holdId, ?int $vehicleId, ?int $bookingId, ?Throwable $exception = null): void
    {
        $this->line(json_encode([
            'success' => $success,
            'hold_id' => $holdId,
            'vehicle_id' => $vehicleId,
            'booking_id' => $bookingId,
            'error' => $exception?->getMessage(),
            'error_class' => $exception !== null ? $exception::class : null,
        ]));
    }
}
