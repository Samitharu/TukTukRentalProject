<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Modules\Pricing\Services\PricingService;

/**
 * The simple admin package form: name, category, vehicle type, "how is it
 * charged" and one price — everything else optional.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->seed(AdminDatabaseSeeder::class);
    config(['admin.two_factor_enforced_roles' => []]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->tukTukLine = ProductCategory::query()->where('name->en', 'Tuk Tuk Rental')->firstOrFail();
    $this->rentLine = ProductCategory::query()->where('name->en', 'Vehicle Rent')->firstOrFail();
    $this->tukTuk = VehicleCategory::query()->where('name->en', 'Tuk Tuk')->firstOrFail();
    $this->suv = VehicleCategory::query()->where('name->en', 'SUV')->firstOrFail();
});

function createSimplePackage(object $test, array $fields): Package
{
    $test->actingAs($test->admin)->post('/control-panel/packages', array_merge([
        'name' => ['en' => 'Test Package'],
        'product_category_id' => $test->tukTukLine->id,
        'vehicle_type_id' => $test->tukTuk->id,
    ], $fields))->assertSessionHasNoErrors()->assertRedirect('/control-panel/packages');

    return Package::query()->latest('id')->firstOrFail();
}

it('has exactly the Tuk Tuk, SUV and Bike vehicle types', function (): void {
    expect(VehicleCategory::query()->where('kind', VehicleCategory::KIND_VEHICLE)->orderBy('sort_order')->get()->map(fn ($t) => $t->getTranslation('name', 'en'))->all())
        ->toBe(['Tuk Tuk', 'SUV', 'Bike'])
        ->and($this->tukTuk->product_category_id)->toBe($this->tukTukLine->id)
        ->and($this->suv->product_category_id)->toBe($this->rentLine->id);
});

it('creates a per-day package from just a name, vehicle type and price', function (): void {
    $package = createSimplePackage($this, ['charge' => 'per_day', 'price' => '18']);

    expect($package->pricing_model)->toBe(Package::MODEL_PER_DAY)
        ->and($package->categories()->pluck('vehicle_categories.id')->all())->toBe([$this->tukTuk->id])
        ->and($package->pricingTiers()->count())->toBe(1)
        ->and(app(PricingService::class)->calculate($package, CarbonImmutable::parse('2027-01-10'), 3)->total)->toBe(54.0);
});

it('creates a per-hour package', function (): void {
    $package = createSimplePackage($this, ['charge' => 'per_hour', 'price' => '6', 'min_hours' => '2', 'max_hours' => '8']);

    expect($package->isHourly())->toBeTrue()
        ->and([$package->min_hours, $package->max_hours, $package->min_days, $package->max_days])->toBe([2, 8, 1, 1])
        ->and(app(PricingService::class)->calculate($package, CarbonImmutable::parse('2027-01-10'), 1, hours: 3)->total)->toBe(18.0);
});

it('creates a km package: one price including km, extra km charged', function (): void {
    $package = createSimplePackage($this, [
        'product_category_id' => $this->rentLine->id,
        'vehicle_type_id' => $this->suv->id,
        'charge' => 'km_package', 'price' => '30', 'included_km' => '100', 'extra_km_rate' => '0.30', 'package_days' => '1',
    ]);

    expect($package->pricing_model)->toBe(Package::MODEL_FIXED_BUNDLE)
        ->and($package->hasKmAllowance())->toBeTrue()
        ->and($package->includedKmFor(1))->toBe(100)
        ->and([$package->min_days, $package->max_days])->toBe([1, 1])
        ->and(app(PricingService::class)->calculate($package, CarbonImmutable::parse('2027-01-10'), 1)->total)->toBe(30.0);
});

it('asks for the km details of a km package, and for a price', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', [
        'name' => ['en' => 'KM'], 'product_category_id' => $this->tukTukLine->id, 'charge' => 'km_package',
    ])->assertSessionHasErrors(['price', 'included_km', 'extra_km_rate']);
});

it('only offers ways of charging that fit the category', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', [
        'name' => ['en' => 'Wrong'], 'product_category_id' => $this->tukTukLine->id, 'charge' => 'per_night', 'price' => '10',
    ])->assertSessionHasErrors('charge');
});

it('edits a package with the same simple form and updates its one price', function (): void {
    $package = createSimplePackage($this, ['charge' => 'per_day', 'price' => '18']);

    $this->actingAs($this->admin)->get("/control-panel/packages/{$package->id}/edit")->assertOk()
        ->assertSee('How is it charged?')->assertSee('value="18.00"', false)->assertSee('More options (optional)');

    $this->actingAs($this->admin)->put("/control-panel/packages/{$package->id}", [
        'name' => ['en' => 'Test Package'], 'product_category_id' => $this->tukTukLine->id,
        'vehicle_type_id' => '', 'charge' => 'per_hour', 'price' => '7', 'min_hours' => '1',
    ])->assertSessionHasNoErrors();

    $package->refresh();
    expect($package->isHourly())->toBeTrue()
        ->and($package->pricingTiers()->sole()->price)->toEqual(7.0)
        ->and($package->categories()->count())->toBe(0);
});

it('keeps length-based prices of an older package untouched', function (): void {
    $package = Package::factory()->create(['product_category_id' => $this->tukTukLine->id, 'pricing_model' => Package::MODEL_TIERED]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => 3, 'price' => 25]);
    $package->pricingTiers()->create(['min_days' => 4, 'max_days' => null, 'price' => 20]);

    $this->actingAs($this->admin)->put("/control-panel/packages/{$package->id}", [
        'name' => ['en' => 'Island Explorer'], 'product_category_id' => $this->tukTukLine->id, 'charge' => 'per_day',
    ])->assertSessionHasNoErrors();

    expect($package->pricingTiers()->count())->toBe(2);
});

it('shows a simple create form', function (): void {
    $this->actingAs($this->admin)->get('/control-panel/packages/create')->assertOk()
        ->assertSee('How is it charged?')
        ->assertSee('KM package')
        ->assertSee('Vehicle type')
        ->assertSee('SUV')
        ->assertDontSee('Pricing type');
});
