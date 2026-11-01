<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * Tuk Tuk and Vehicle Rent are both rented by the day (kind "vehicle"),
 * so the product line is what keeps them apart: an unrestricted tuk tuk
 * package must never hand out a scooter, and the booking wizard for one
 * line never lists the other line's packages.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    $this->tukTukLine = ProductCategory::query()->where('name->en', 'Tuk Tuk Rental')->firstOrFail();
    $this->rentLine = ProductCategory::query()->where('name->en', 'Vehicle Rent')->firstOrFail();

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
    expect($this->tukTukPackage->eligibleVehicleIds())->toBe([$this->tukTuk->id])
        ->and($this->scooterPackage->eligibleVehicleIds())->toBe([$this->scooter->id]);
});

it('still lets unit types not yet in a line serve every line of their kind', function (): void {
    $legacy = Vehicle::factory()->create(['category_id' => VehicleCategory::factory()->create(['product_category_id' => null])->id]);

    expect($this->tukTukPackage->eligibleVehicleIds())->toContain($legacy->id)
        ->and($this->scooterPackage->eligibleVehicleIds())->toContain($legacy->id);
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

    expect(\Modules\Booking\Models\Booking::query()->sole()->vehicle_id)->toBe($this->scooter->id);
});

it('lists tuk tuks and other rental vehicles on separate public pages', function (): void {
    $this->get('/en/tuk-tuks')->assertOk()->assertSee($this->tukTuk->name)->assertDontSee('Honda Dio');
    $this->get('/en/vehicles')->assertOk()->assertSee('Honda Dio')->assertDontSee((string) $this->tukTuk->name);
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
