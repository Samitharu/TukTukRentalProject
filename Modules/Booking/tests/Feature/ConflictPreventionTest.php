<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Pricing\DataObjects\PriceBreakdown;

beforeEach(function (): void {
    $this->service = app(BookingService::class);
    $this->vehicle = Vehicle::factory()->create();
});

function confirmedBookingFor(BookingService $service, int $vehicleId, string $start, string $end): Booking
{
    $hold = $service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => $start,
        'end_at' => $end,
        'vehicle_id' => $vehicleId,
    ]);

    $price = new PriceBreakdown('USD', 2, 50, 0, [], 0, 0, null, 0, 50, 0);

    return $service->confirmHold($hold, ['email' => Str::uuid().'@example.test', 'full_name' => 'Tester'], $price);
}

it('rejects a new hold that overlaps a confirmed booking', function (): void {
    confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-09-04',
        'end_at' => '2026-09-08',
        'vehicle_id' => $this->vehicle->id,
    ]);
})->throws(NoVehicleAvailableException::class);

it('allows a new booking on the same vehicle for genuinely non-overlapping dates', function (): void {
    confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');

    $second = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-06', '2026-09-10');

    expect($second)->toBeInstanceOf(Booking::class);
});

it('rejects changing a booking\'s dates onto another booking\'s dates for the same vehicle', function (): void {
    confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-10', '2026-09-15');
    $booking = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-20', '2026-09-25');

    $this->service->changeDates($booking, CarbonImmutable::parse('2026-09-11'), CarbonImmutable::parse('2026-09-14'));
})->throws(NoVehicleAvailableException::class);

it('allows changing a booking\'s dates when the new range is free, and releases the old slots', function (): void {
    $booking = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');

    $updated = $this->service->changeDates($booking, CarbonImmutable::parse('2026-09-10'), CarbonImmutable::parse('2026-09-12'));

    expect($updated->start_at->toDateString())->toBe('2026-09-10');

    // The old dates are free again — a new booking can use them.
    $again = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');
    expect($again)->toBeInstanceOf(Booking::class);
});

it('rejects reassigning a booking onto a vehicle that is already busy for those dates', function (): void {
    $busyVehicle = Vehicle::factory()->create();
    confirmedBookingFor($this->service, $busyVehicle->id, '2026-09-01', '2026-09-05');

    $booking = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');

    $this->service->reassignVehicle($booking, $busyVehicle->id);
})->throws(NoVehicleAvailableException::class);

it('allows reassigning to a free vehicle and releases the original vehicle\'s slots', function (): void {
    $newVehicle = Vehicle::factory()->create();
    $booking = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');

    $updated = $this->service->reassignVehicle($booking, $newVehicle->id);

    expect($updated->vehicle_id)->toBe($newVehicle->id);

    // The original vehicle is free again for those dates.
    $again = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');
    expect($again)->toBeInstanceOf(Booking::class);
});

it('cancelling a booking frees its vehicle for the same dates', function (): void {
    $booking = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');

    $this->service->cancel($booking, 'Customer requested cancellation');

    $again = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');
    expect($again)->toBeInstanceOf(Booking::class);
});

it('records a status-history entry for every state change', function (): void {
    $booking = confirmedBookingFor($this->service, $this->vehicle->id, '2026-09-01', '2026-09-05');

    $this->service->cancel($booking, 'No longer needed');

    $history = $booking->statusHistory()->get();
    $cancelEntry = $history->firstWhere('to_status', Booking::STATUS_CANCELLED);

    expect($history)->toHaveCount(2) // created (null->confirmed), cancelled
        ->and($cancelEntry)->not->toBeNull()
        ->and($cancelEntry->reason)->toBe('No longer needed');
});
