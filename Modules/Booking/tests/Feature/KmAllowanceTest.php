<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingExtraCharge;
use Modules\Fleet\Models\Vehicle;
use Modules\Package\Models\Package;
use Spatie\Permission\Models\Permission;

/**
 * Km-bundle packages: the allowance and extra-km rate are frozen onto the
 * booking when it is made, and recording the odometer at return keeps a
 * single "extra km" charge in step with the km driven beyond it.
 */
beforeEach(function (): void {
    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['bookings.view', 'bookings.manage']);
    $this->actingAs($this->admin);

    $this->vehicle = Vehicle::factory()->create();
    $this->package = Package::factory()->create([
        'pricing_model' => Package::MODEL_PER_DAY,
        'included_km' => 100,
        'included_km_per_day' => true,
        'extra_km_rate' => 0.5,
    ]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
});

function bookKmPackage(array $overrides = []): Booking
{
    test()->post('/control-panel/bookings', array_merge([
        'package_id' => test()->package->id,
        'vehicle_id' => (string) test()->vehicle->id,
        'start_date' => '2027-03-01',
        'end_date' => '2027-03-03', // 3 days → 300 km included
        'pickup_type' => 'office',
        'full_name' => 'Km Customer',
        'email' => 'km@example.test',
    ], $overrides))->assertSessionHasNoErrors();

    return Booking::query()->latest('id')->firstOrFail();
}

it('freezes the km allowance for the booking length onto the booking', function (): void {
    $booking = bookKmPackage();

    // A later package change must not change what this customer pays.
    $this->package->update(['included_km' => 50, 'extra_km_rate' => 2]);

    expect($booking->fresh()->included_km)->toBe(300)
        ->and((float) $booking->fresh()->extra_km_rate)->toEqualWithDelta(0.5, 0.001);
});

it('charges km driven over the allowance once the odometer is recorded, and recalculates on correction', function (): void {
    $booking = bookKmPackage();

    $this->put("/control-panel/bookings/{$booking->id}/odometer", ['odometer_start' => '12000', 'odometer_end' => '12450'])
        ->assertSessionHasNoErrors();

    $charge = $booking->extraCharges()->where('type', BookingExtraCharge::TYPE_EXTRA_KM)->sole();
    expect((float) $charge->amount)->toEqualWithDelta(75.0, 0.01) // (450 − 300) × 0.5
        ->and((float) $booking->fresh()->total_amount)->toEqualWithDelta(60.0, 0.01); // booking price itself unchanged

    // Corrected reading within the allowance: the charge goes away.
    $this->put("/control-panel/bookings/{$booking->id}/odometer", ['odometer_start' => '12000', 'odometer_end' => '12250'])
        ->assertSessionHasNoErrors();

    expect($booking->extraCharges()->count())->toBe(0);
});

it('uses a total allowance when the package km is not per day', function (): void {
    $this->package->update(['included_km_per_day' => false]);

    $booking = bookKmPackage();

    expect($booking->included_km)->toBe(100);
});

it('refuses a return reading below the pickup reading', function (): void {
    $booking = bookKmPackage();

    $this->put("/control-panel/bookings/{$booking->id}/odometer", ['odometer_start' => '5000', 'odometer_end' => '4000'])
        ->assertSessionHasErrors('odometer_end');
});

it('shows the odometer form only for bookings with a km allowance', function (): void {
    $booking = bookKmPackage();
    $this->get("/control-panel/bookings/{$booking->id}")->assertOk()->assertSee('Odometer');

    $unlimited = Package::factory()->create();
    $unlimited->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
    $other = bookKmPackage(['package_id' => $unlimited->id, 'start_date' => '2027-04-01', 'end_date' => '2027-04-02', 'email' => 'other@example.test']);

    expect($other->included_km)->toBeNull();
    $this->get("/control-panel/bookings/{$other->id}")->assertOk()->assertDontSee('Odometer');
});
