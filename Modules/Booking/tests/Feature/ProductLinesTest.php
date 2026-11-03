<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Spatie\Permission\Models\Permission;

/**
 * Tuk Tuk and Vehicle Rent are both rented by the day (kind "vehicle"),
 * so the product line is what keeps them apart: the booking wizard for one
 * line never lists the other line's packages. No line registers units any
 * more: every booking is accepted and reserves nothing. A line flagged
 * otherwise (here, for the test) still uses the reservation engine.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    // Created by migrations, before this test's locale existed: no slugs yet.
    ProductCategory::query()->get()->each->syncSlugs();

    $this->tukTukLine = ProductCategory::query()->where('name->en', 'Tuk Tuk Rental')->firstOrFail();
    $this->rentLine = ProductCategory::query()->where('name->en', 'Vehicle Rent')->firstOrFail();
    expect(ProductCategory::query()->where('unlimited_units', false)->count())->toBe(0); // nothing is registered

    // A line that still reserves registered units, to keep lines apart.
    $this->rentLine->update(['unlimited_units' => false]);

    $this->tukTukType = VehicleCategory::factory()->create(['product_category_id' => $this->tukTukLine->id]);
    $this->scooterType = VehicleCategory::factory()->create(['name' => ['en' => 'Scooters'], 'product_category_id' => $this->rentLine->id]);
    $this->tukTuk = Vehicle::factory()->create(['category_id' => $this->tukTukType->id]);
    $this->scooter = Vehicle::factory()->create(['category_id' => $this->scooterType->id, 'name' => ['en' => 'Honda Dio']]);

    $this->tukTukPackage = Package::factory()->create(['name' => ['en' => 'Tuk Tuk Daily'], 'product_category_id' => $this->tukTukLine->id]);
    $this->scooterPackage = Package::factory()->create(['name' => ['en' => 'Scooter Daily'], 'product_category_id' => $this->rentLine->id]);

    foreach ([$this->tukTukPackage, $this->scooterPackage] as $package) {
        $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 15]);
    }
});

it('only auto-assigns units of the package\'s own product line', function (): void {
    expect($this->tukTukPackage->eligibleVehicleIds())->toBe([]) // tuk tuks come from partners
        ->and($this->scooterPackage->eligibleVehicleIds())->toBe([$this->scooter->id]);
});

it('still lets unit types not yet in a line serve a line with registered units', function (): void {
    $legacy = Vehicle::factory()->create(['category_id' => VehicleCategory::factory()->create(['product_category_id' => null])->id]);

    expect($this->scooterPackage->eligibleVehicleIds())->toContain($legacy->id);
});

it('lists only the chosen line\'s packages in the booking wizard', function (): void {
    $this->get('/en/booking/start?line='.$this->rentLine->id)->assertOk()->assertSee('Vehicle Rent');
    $this->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-03'])->assertRedirect('/en/booking/package');

    $this->get('/en/booking/package')->assertOk()->assertSee('Scooter Daily')->assertDontSee('Tuk Tuk Daily');

    // Switching to the Tuk Tuk line keeps the dates but swaps the packages.
    $this->get('/en/booking/start?line='.$this->tukTukLine->id)->assertOk();
    $this->get('/en/booking/package')->assertOk()->assertSee('Tuk Tuk Daily')->assertDontSee('Scooter Daily');
});

it('defaults a fresh booking to the Tuk Tuk line', function (): void {
    $this->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-03']);

    $this->get('/en/booking/package')->assertOk()->assertSee('Tuk Tuk Daily')->assertDontSee('Scooter Daily');
});

it('books a scooter, never a tuk tuk, through the Vehicle Rent line', function (): void {
    $this->get('/en/booking/start?line='.$this->rentLine->id);
    $this->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-03']);
    $this->post('/en/booking/package', ['package_id' => $this->scooterPackage->id])->assertRedirect('/en/booking/addons');
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', [
        'first_name' => 'Sam', 'last_name' => 'Rider', 'email' => 'sam@example.test', 'phone' => '+44 7911 000000',
        'nationality' => 'GB', 'passport_number' => 'P1', 'has_valid_licence' => '1', 'has_international_permit' => '0', 'marketing_opt_in' => '0',
    ]);
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    expect(Booking::query()->sole()->vehicle_id)->toBe($this->scooter->id);
});

function bookTukTuk(object $test, string $email): Booking
{
    $test->get('/en/booking/start?line='.$test->tukTukLine->id)->assertOk();
    $test->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-03'])->assertRedirect('/en/booking/package');
    $test->get('/en/booking/package')->assertOk()->assertDontSee('Fully booked for your dates')->assertDontSee('disabled', false);
    $test->post('/en/booking/package', ['package_id' => $test->tukTukPackage->id])->assertRedirect('/en/booking/addons');
    $test->post('/en/booking/addons', []);
    $test->post('/en/booking/details', [
        'first_name' => 'Tuk', 'last_name' => 'Rider', 'email' => $email, 'phone' => '+44 7911 000000',
        'nationality' => 'GB', 'passport_number' => 'P1', 'has_valid_licence' => '1', 'has_international_permit' => '0', 'marketing_opt_in' => '0',
    ]);
    $test->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    return Booking::query()->latest('id')->firstOrFail();
}

it('takes any number of tuk tuk bookings on the same dates, reserving nothing', function (): void {
    $bookings = collect(range(1, 3))->map(fn (int $i) => bookTukTuk($this, "rider{$i}@example.test"));

    expect(Booking::query()->where('status', Booking::STATUS_CONFIRMED)->count())->toBe(3)
        ->and($bookings->every(fn (Booking $booking) => $booking->hasNoUnit()))->toBeTrue()
        ->and(VehicleReservationSlot::query()->count())->toBe(0);

    $this->get('/en/booking/confirmation/'.$bookings->first()->reference)->assertOk();
    $this->get('/en/booking/confirmation/'.$bookings->first()->reference.'/receipt')->assertOk();
    $this->get('/en/booking/status/'.$bookings->first()->reference)->assertOk();
});

it('takes tuk tuk bookings with no vehicles on file at all', function (): void {
    Vehicle::query()->whereKey($this->tukTuk->id)->delete();

    expect(bookTukTuk($this, 'nofleet@example.test')->hasNoUnit())->toBeTrue();
});

it('books a stay with no registered cabana, still counting nights', function (): void {
    $stays = ProductCategory::query()->where('name->en', 'Stays')->firstOrFail();
    $package = Package::factory()->create(['name' => ['en' => 'Beach Stay'], 'product_category_id' => $stays->id]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 40]);

    foreach (['one@example.test', 'two@example.test'] as $email) {
        $this->get('/en/booking/start?line='.$stays->id)->assertOk();
        $this->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-04'])->assertRedirect('/en/booking/package');
        $this->post('/en/booking/package', ['package_id' => $package->id])->assertRedirect('/en/booking/addons');
        $this->post('/en/booking/addons', []);
        $this->post('/en/booking/details', [
            'first_name' => 'Guest', 'last_name' => 'One', 'email' => $email, 'phone' => '+44 7911 000000',
            'nationality' => 'GB', 'passport_number' => 'P1', 'marketing_opt_in' => '0',
        ]);
        $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();
    }

    $booking = Booking::query()->latest('id')->firstOrFail();

    expect(Booking::query()->count())->toBe(2)
        ->and($booking->hasNoUnit())->toBeTrue()
        ->and($booking->isStay())->toBeTrue()
        ->and($booking->checkOutDate()->toDateString())->toBe('2027-02-04')
        ->and((float) $booking->total_amount)->toBe(120.0); // 3 nights

    $this->get('/en/booking/confirmation/'.$booking->reference)->assertOk()->assertSee('04 Feb 2027');
    $this->get('/en/booking/confirmation/'.$booking->reference.'/receipt')->assertOk();
});

it('has no vehicle or unit registration pages', function (): void {
    Permission::findOrCreate('fleet.view');
    Permission::findOrCreate('fleet.manage');
    Permission::findOrCreate('bookings.view');
    $staff = User::factory()->create();
    $staff->givePermissionTo(['fleet.view', 'fleet.manage', 'bookings.view']);

    expect(Route::has('admin.fleet.vehicles.index'))->toBeFalse()
        ->and(Route::has('admin.fleet.categories.index'))->toBeFalse();

    $this->actingAs($staff)->get('/control-panel/fleet/vehicles')->assertNotFound();
    $this->actingAs($staff)->get('/control-panel/bookings')->assertOk()
        ->assertDontSee('Vehicles &amp; Stays', false)
        ->assertDontSee('Unit Types');
});

it('does not assign a vehicle to a tuk tuk booking even if staff pick one', function (): void {
    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    $staff = User::factory()->create();
    $staff->givePermissionTo(['bookings.view', 'bookings.manage']);

    $payload = [
        'package_id' => $this->tukTukPackage->id, 'vehicle_id' => (string) $this->tukTuk->id,
        'start_date' => '2027-02-01', 'end_date' => '2027-02-03',
        'full_name' => 'Walk In', 'email' => 'walkin@example.test',
    ];

    $this->actingAs($staff)->postJson('/control-panel/bookings/quote', $payload)->assertOk()->assertJson(['unlimited_units' => true]);
    $this->actingAs($staff)->post('/control-panel/bookings', $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->actingAs($staff)->post('/control-panel/bookings', [...$payload, 'email' => 'walkin2@example.test'])->assertSessionHasNoErrors();

    $booking = Booking::query()->latest('id')->firstOrFail();
    expect($booking->hasNoUnit())->toBeTrue()
        ->and(Booking::query()->count())->toBe(2);

    $this->actingAs($staff)->get('/control-panel/bookings')->assertOk()->assertSee('Tuk Tuk Rental');
    $this->actingAs($staff)->get('/control-panel/bookings/'.$booking->id)->assertOk()
        ->assertSee('Tuk Tuk Rental')
        ->assertDontSee('Reassign vehicle');
    $this->actingAs($staff)->put('/control-panel/bookings/'.$booking->id.'/vehicle', ['vehicle_id' => $this->scooter->id])->assertSessionHasErrors('vehicle_id');

    // Moving dates needs no free vehicle.
    $this->actingAs($staff)->put('/control-panel/bookings/'.$booking->id.'/dates', ['start_date' => '2027-03-01', 'end_date' => '2027-03-02'])->assertSessionHasNoErrors();
    expect($booking->fresh()->start_at->toDateString())->toBe('2027-03-01');
});

it('still refuses a scooter that is already out: Vehicle Rent is not unlimited', function (): void {
    app(BookingService::class)->createHold([
        'hold_key' => (string) Str::uuid(), 'start_at' => '2027-02-01', 'end_at' => '2027-02-03', 'vehicle_id' => $this->scooter->id,
    ]);

    $this->get('/en/booking/start?line='.$this->rentLine->id);
    $this->post('/en/booking/start', ['start_date' => '2027-02-02', 'end_date' => '2027-02-02']);

    $this->get('/en/booking/package')->assertOk()->assertSee('Fully booked for your dates');
    $this->post('/en/booking/package', ['package_id' => $this->scooterPackage->id])->assertSessionHasErrors('package_id');
});

it('sends /tuk-tuks to the tuk tuk packages and keeps tuk tuks off the vehicle pages', function (): void {
    $this->get('/en/tuk-tuks')->assertRedirect('/en/packages/category/tuk-tuk-rental');
    $this->get('/en/tuk-tuks/'.$this->tukTuk->slugFor('en'))->assertRedirect('/en/tuk-tuks');

    $this->get('/en/vehicles')->assertOk()->assertSee('Honda Dio')->assertDontSee((string) $this->tukTuk->name);
    $this->get('/en')->assertOk()->assertDontSee((string) $this->tukTuk->name);
    expect($this->get('/sitemap.xml')->assertOk()->getContent())->not->toContain('/en/tuk-tuks/'.$this->tukTuk->slugFor('en'));
});

it('opens the packages for /vehicles and /stays when nothing is registered', function (): void {
    Vehicle::query()->delete();

    $this->get('/en/vehicles')->assertRedirect('/en/packages/category/vehicle-rent');
    $this->get('/en/stays')->assertRedirect('/en/packages/category/stays');
    $this->get('/en')->assertOk();
});

it('refuses to restrict a tuk tuk package to another line\'s unit type', function (): void {
    $this->seed(AdminDatabaseSeeder::class);
    config(['admin.two_factor_enforced_roles' => []]);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->post('/control-panel/packages', [
        'name' => ['en' => 'Mixed'],
        'product_category_id' => $this->tukTukLine->id,
        'pricing_model' => Package::MODEL_PER_DAY,
        'min_days' => 1,
        'sort_order' => 0,
        'category_ids' => [$this->scooterType->id],
    ])->assertSessionHasErrors('category_ids');
});
