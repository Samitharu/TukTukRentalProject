<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Availability\Models\AvailabilityBlackout;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Availability\Services\AvailabilityService;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Fleet\Models\VehicleMaintenanceLog;

beforeEach(function (): void {
    $this->service = new AvailabilityService();
    $this->vehicle = Vehicle::factory()->create();
});

it('considers a vehicle with no blackouts, maintenance, or reservations free', function (): void {
    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-05')))
        ->toBeTrue();
});

it('blocks a range that overlaps an admin blackout', function (): void {
    AvailabilityBlackout::query()->create([
        'vehicle_id' => $this->vehicle->id,
        'starts_on' => '2026-06-03',
        'ends_on' => '2026-06-04',
        'reason' => 'Inspection',
    ]);

    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-05')))
        ->toBeFalse();
});

it('treats a business-wide blackout (null vehicle_id) as blocking every vehicle', function (): void {
    AvailabilityBlackout::query()->create([
        'vehicle_id' => null,
        'starts_on' => '2026-12-25',
        'ends_on' => '2026-12-25',
        'reason' => 'Public holiday',
    ]);

    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-12-24'), CarbonImmutable::parse('2026-12-26')))
        ->toBeFalse();
});

it('does not let a blackout on a different vehicle block this one', function (): void {
    $other = Vehicle::factory()->create();
    AvailabilityBlackout::query()->create([
        'vehicle_id' => $other->id,
        'starts_on' => '2026-06-03',
        'ends_on' => '2026-06-04',
    ]);

    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-05')))
        ->toBeTrue();
});

it('blocks a range that overlaps a maintenance log', function (): void {
    VehicleMaintenanceLog::query()->create([
        'vehicle_id' => $this->vehicle->id,
        'type' => 'Oil change',
        'starts_at' => '2026-06-02 08:00:00',
        'ends_at' => '2026-06-02 17:00:00',
    ]);

    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-03')))
        ->toBeFalse();
});

it('blocks a range that overlaps an existing reservation slot', function (): void {
    VehicleReservationSlot::query()->create([
        'vehicle_id' => $this->vehicle->id,
        'slot_date' => '2026-06-04',
        'holdable_type' => 'placeholder',
        'holdable_id' => 1,
        'created_at' => now(),
    ]);

    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-05')))
        ->toBeFalse();
});

it('allows an adjacent reservation with zero buffer configured', function (): void {
    config(['availability.buffer_days' => 0]);

    VehicleReservationSlot::query()->create([
        'vehicle_id' => $this->vehicle->id,
        'slot_date' => '2026-06-05',
        'holdable_type' => 'placeholder',
        'holdable_id' => 1,
        'created_at' => now(),
    ]);

    // New request 2026-06-01..2026-06-04 does not touch 2026-06-05.
    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-04')))
        ->toBeTrue();
});

it('extends the conflict window by the configured buffer on both sides', function (): void {
    config(['availability.buffer_days' => 1]);

    VehicleReservationSlot::query()->create([
        'vehicle_id' => $this->vehicle->id,
        'slot_date' => '2026-06-05',
        'holdable_type' => 'placeholder',
        'holdable_id' => 1,
        'created_at' => now(),
    ]);

    // With a 1-day buffer, a booking ending 2026-06-04 now conflicts with
    // the 2026-06-05 reservation (needs a cleaning day between them).
    expect($this->service->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-04')))
        ->toBeFalse();
});

it('returns only vehicles in the category that are actually free', function (): void {
    $category = VehicleCategory::factory()->create();
    $free = Vehicle::factory()->create(['category_id' => $category->id]);
    $taken = Vehicle::factory()->create(['category_id' => $category->id]);
    $inactive = Vehicle::factory()->create(['category_id' => $category->id, 'status' => Vehicle::STATUS_RETIRED]);

    AvailabilityBlackout::query()->create([
        'vehicle_id' => $taken->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-05',
    ]);

    $result = $this->service->availableVehiclesInCategory($category->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-05'));

    expect($result->pluck('id')->all())->toBe([$free->id]);
});

it('enforces minimum notice hours', function (): void {
    config(['availability.minimum_notice_hours' => 4]);
    $now = CarbonImmutable::parse('2026-06-01 10:00:00');

    expect($this->service->meetsMinimumNotice($now->addHours(5), $now))->toBeTrue()
        ->and($this->service->meetsMinimumNotice($now->addHours(2), $now))->toBeFalse();
});

it('enforces the maximum advance booking window', function (): void {
    config(['availability.maximum_advance_days' => 30]);
    $now = CarbonImmutable::parse('2026-06-01');

    expect($this->service->withinAdvanceWindow($now->addDays(10), $now))->toBeTrue()
        ->and($this->service->withinAdvanceWindow($now->addDays(60), $now))->toBeFalse();
});
