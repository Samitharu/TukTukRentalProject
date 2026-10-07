<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Booking\Models\Booking;
use Modules\Package\Models\Package;
use Spatie\Permission\Models\Permission;

/**
 * Regression: deleting a vehicle (soft delete, so the bookings FK never
 * objects) made $booking->vehicle null, and the admin bookings list and
 * detail pages 500'd on ->adminLabel() for every booking of that vehicle.
 * Customers and packages soft-delete the same way.
 */
beforeEach(function (): void {
    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo(['bookings.view', 'bookings.manage']);
    $this->actingAs($admin);

    $this->booking = Booking::factory()->create(['package_id' => Package::factory()]);
});

it('still lists and shows a booking whose vehicle, customer and package were deleted', function (): void {
    $plate = $this->booking->vehicle->plate_no;
    $name = $this->booking->customer->full_name;

    $this->booking->vehicle->delete();
    $this->booking->customer->delete();
    $this->booking->package->delete();

    $this->get('/control-panel/bookings')->assertOk()->assertSee($name);
    $this->get('/control-panel/bookings/'.$this->booking->id)->assertOk()->assertSee($name);

    expect($this->booking->fresh()->vehicle?->plate_no)->toBe($plate);
});
