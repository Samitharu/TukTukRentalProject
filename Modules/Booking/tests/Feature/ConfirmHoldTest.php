<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Booking\Exceptions\HoldExpiredException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingHold;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Pricing\DataObjects\PriceBreakdown;

beforeEach(function (): void {
    $this->service = app(BookingService::class);
    $this->vehicle = Vehicle::factory()->create();
});

function samplePriceBreakdown(): PriceBreakdown
{
    return new PriceBreakdown(
        currencyCode: 'USD',
        days: 2,
        baseAmount: 50,
        seasonalAdjustment: 0,
        addonLines: [],
        addonsTotal: 0,
        deliveryFee: 0,
        couponCode: null,
        couponDiscount: 0,
        total: 50,
        depositAmount: 0,
    );
}

it('converts an active hold into a confirmed booking and re-points the same slot rows', function (): void {
    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-02',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $slotIdsBefore = VehicleReservationSlot::query()->where('vehicle_id', $this->vehicle->id)->pluck('id')->sort()->values();

    $booking = $this->service->confirmHold($hold, [
        'email' => 'guest@example.test',
        'full_name' => 'Guest Traveller',
    ], samplePriceBreakdown());

    expect($booking)->toBeInstanceOf(Booking::class)
        ->and($booking->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->reference)->toMatch('/^MTR-/');

    $hold->refresh();
    expect($hold->status)->toBe(BookingHold::STATUS_CONVERTED);

    $slotIdsAfter = VehicleReservationSlot::query()->where('vehicle_id', $this->vehicle->id)->pluck('id')->sort()->values();
    expect($slotIdsAfter->all())->toBe($slotIdsBefore->all()); // same rows, just re-pointed

    $slot = VehicleReservationSlot::query()->where('vehicle_id', $this->vehicle->id)->first();
    expect($slot->holdable_id)->toBe($booking->id)
        ->and($slot->holdable_type)->toBe('booking');
});

it('goes straight to confirmed, skipping pending_payment, while online payment is disabled', function (): void {
    config(['pricing.online_payment_enabled' => false]);

    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-02',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $booking = $this->service->confirmHold($hold, ['email' => 'a@example.test', 'full_name' => 'A'], samplePriceBreakdown());

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('is idempotent: confirming the same hold twice returns the same booking', function (): void {
    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-02',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $customerData = ['email' => 'dup@example.test', 'full_name' => 'Dup'];
    $first = $this->service->confirmHold($hold, $customerData, samplePriceBreakdown());
    $second = $this->service->confirmHold($hold, $customerData, samplePriceBreakdown());

    expect($second->id)->toBe($first->id)
        ->and(Booking::query()->count())->toBe(1);
});

it('refuses to confirm an expired hold', function (): void {
    $hold = $this->service->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2026-07-01',
        'end_at' => '2026-07-02',
        'vehicle_id' => $this->vehicle->id,
    ]);

    $hold->update(['expires_at' => now()->subMinute()]);

    $this->service->confirmHold($hold, ['email' => 'x@example.test', 'full_name' => 'X'], samplePriceBreakdown());
})->throws(HoldExpiredException::class);

it('matches an existing customer by email instead of creating a duplicate', function (): void {
    $hold1 = $this->service->createHold(['hold_key' => (string) Str::uuid(), 'start_at' => '2026-07-01', 'end_at' => '2026-07-02', 'vehicle_id' => $this->vehicle->id]);
    $booking1 = $this->service->confirmHold($hold1, ['email' => 'repeat@example.test', 'full_name' => 'First Trip'], samplePriceBreakdown());

    $vehicle2 = Vehicle::factory()->create();
    $hold2 = $this->service->createHold(['hold_key' => (string) Str::uuid(), 'start_at' => '2026-08-01', 'end_at' => '2026-08-02', 'vehicle_id' => $vehicle2->id]);
    $booking2 = $this->service->confirmHold($hold2, ['email' => 'repeat@example.test', 'full_name' => 'Second Trip'], samplePriceBreakdown());

    expect($booking2->customer_id)->toBe($booking1->customer_id)
        ->and(\Modules\Customer\Models\Customer::query()->count())->toBe(1);
});
