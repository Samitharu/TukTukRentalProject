<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Booking\Models\Booking;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Spatie\Permission\Models\Permission;

/**
 * Regression coverage for the admin "new manual booking" HTTP flow
 * specifically — as opposed to the BookingService unit/feature tests,
 * which call the service directly with already-correctly-typed PHP values
 * and so never exercised the string-typed values every HTTP form submission
 * actually produces. Two real bugs were only caught by testing this path
 * live: Carbon's diffInDays() returning float where an int was required,
 * and an un-cast string vehicle_id reaching a strictly-typed int parameter.
 */
beforeEach(function (): void {
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo('bookings.manage');
    $this->actingAs($admin);

    $this->package = Package::factory()->create();
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
    $this->vehicle = Vehicle::factory()->create();
});

function submitManualBooking(array $overrides = []): \Illuminate\Testing\TestResponse
{
    return test()->post('/control-panel/bookings', array_merge([
        'package_id' => test()->package->id,
        'vehicle_id' => (string) test()->vehicle->id, // HTTP form values are always strings
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-03', // 3-day range: exercises the diffInDays() float bug
        'pickup_type' => 'office',
        'full_name' => 'HTTP Test Customer',
        'email' => 'http-test@example.test',
    ], $overrides));
}

it('creates a booking through the real HTTP form submission without a type error', function (): void {
    $response = submitManualBooking();

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $booking = Booking::query()->first();
    expect($booking)->not->toBeNull()
        ->and($booking->vehicle_id)->toBe($this->vehicle->id)
        ->and($booking->total_amount)->toEqualWithDelta(60.0, 0.01); // 20/day * 3 days
});

it('rejects a second HTTP submission for the same vehicle and overlapping dates', function (): void {
    submitManualBooking()->assertSessionHasNoErrors();

    $response = submitManualBooking(['email' => 'second@example.test', 'start_date' => '2026-12-02', 'end_date' => '2026-12-04']);

    $response->assertSessionHasErrors();
    expect(Booking::query()->count())->toBe(1);
});

it('auto-assigns a vehicle from the category when vehicle_id is left blank over HTTP', function (): void {
    $response = submitManualBooking(['vehicle_id' => '']);

    $response->assertSessionHasNoErrors();
    expect(Booking::query()->first()?->vehicle_id)->not->toBeNull();
});
