<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;

beforeEach(function (): void {
    $this->service = app(BookingService::class);
    $this->vehicle = Vehicle::factory()->create();
});

it('does not release a hold that has not expired yet', function (): void {
    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $this->artisan('booking:release-expired-holds')->assertSuccessful();

    expect(BookingHold::query()->first()->status)->toBe(BookingHold::STATUS_ACTIVE)
        ->and(VehicleReservationSlot::query()->where('vehicle_id', $this->vehicle->id)->count())->toBe(3);
});

it('releases an expired hold and frees its reserved slots', function (): void {
    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $this->vehicle->id,
    ]);
    $hold->update(['expires_at' => now()->subMinute()]);

    $this->artisan('booking:release-expired-holds')->assertSuccessful();

    $hold->refresh();
    expect($hold->status)->toBe(BookingHold::STATUS_EXPIRED)
        ->and(VehicleReservationSlot::query()->where('vehicle_id', $this->vehicle->id)->count())->toBe(0);
});

it('lets a new hold take the same dates once the old one has been released', function (): void {
    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $this->vehicle->id,
    ]);
    $hold->update(['expires_at' => now()->subMinute()]);

    $this->artisan('booking:release-expired-holds')->assertSuccessful();

    $newHold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $this->vehicle->id,
    ]);

    expect($newHold->status)->toBe(BookingHold::STATUS_ACTIVE);
});
