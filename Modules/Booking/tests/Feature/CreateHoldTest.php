<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;

beforeEach(function (): void {
    $this->service = app(BookingService::class);
    $this->vehicle = Vehicle::factory()->create();
});

it('creates a hold and reserves a slot row for every day in the range', function (): void {
    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $this->vehicle->id,
    ]);

    expect($hold)->toBeInstanceOf(BookingHold::class)
        ->and($hold->status)->toBe(BookingHold::STATUS_ACTIVE);

    $slotDates = VehicleReservationSlot::query()
        ->where('vehicle_id', $this->vehicle->id)
        ->pluck('slot_date')
        ->map(fn ($d) => $d->toDateString())
        ->sort()
        ->values()
        ->all();

    expect($slotDates)->toBe(['2026-07-01', '2026-07-02', '2026-07-03']);
});

it('rejects a hold that overlaps an already-held range on the same vehicle', function (): void {
    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-05',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-03',
        'end_at' => '2026-07-06',
        'vehicle_id' => $this->vehicle->id,
    ]);
})->throws(NoVehicleAvailableException::class);

it('allows a hold immediately adjacent to another with zero buffer configured', function (): void {
    config(['availability.buffer_days' => 0]);

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $second = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-04',
        'end_at' => '2026-07-06',
        'vehicle_id' => $this->vehicle->id,
    ]);

    expect($second)->toBeInstanceOf(BookingHold::class);
});

it('rejects an adjacent hold when a buffer day is configured', function (): void {
    config(['availability.buffer_days' => 1]);

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-04',
        'end_at' => '2026-07-06',
        'vehicle_id' => $this->vehicle->id,
    ]);
})->throws(NoVehicleAvailableException::class);

it('is idempotent: a repeat submission with the same hold_key returns the same hold, not a new one', function (): void {
    $key = (string) Str::uuid();
    $data = ['hold_key' => $key, 'start_at' => '2026-07-01', 'end_at' => '2026-07-03', 'vehicle_id' => $this->vehicle->id];

    $first = $this->service->createHold($data);
    $second = $this->service->createHold($data);

    expect($second->id)->toBe($first->id)
        ->and(BookingHold::query()->count())->toBe(1)
        ->and(VehicleReservationSlot::query()->where('vehicle_id', $this->vehicle->id)->count())->toBe(3);
});

it('auto-assigns the first available vehicle in a category when none is specified', function (): void {
    $category = VehicleCategory::factory()->create();
    $taken = Vehicle::factory()->create(['category_id' => $category->id]);
    $free = Vehicle::factory()->create(['category_id' => $category->id]);

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $taken->id,
    ]);

    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'category_id' => $category->id,
    ]);

    expect($hold->vehicle_id)->toBe($free->id);
});

it('throws when no vehicle in the category is available', function (): void {
    $category = VehicleCategory::factory()->create();
    $onlyVehicle = Vehicle::factory()->create(['category_id' => $category->id]);

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'vehicle_id' => $onlyVehicle->id,
    ]);

    $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-03',
        'category_id' => $category->id,
    ]);
})->throws(NoVehicleAvailableException::class);
