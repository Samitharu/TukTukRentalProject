<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    Permission::findOrCreate('packages.view');
    Permission::findOrCreate('packages.manage');
    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo(['packages.view', 'packages.manage']);
});

function packagePayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Surf & Stay'],
        // The "Stays" category the migration creates — it makes the package a stay.
        'product_category_id' => ProductCategory::query()->where('kind', 'stay')->value('id'),
        'pricing_model' => 'per_day',
        'min_days' => 2,
        'sort_order' => 0,
    ], $overrides);
}

it('creates a stay package restricted to particular cabanas', function (): void {
    $cabana = Vehicle::factory()->stay()->create();

    $this->actingAs($this->manager)
        ->post('/control-panel/packages', packagePayload(['vehicle_ids' => [$cabana->id]]))
        ->assertSessionHasNoErrors();

    $package = Package::query()->firstOrFail();

    expect($package->isStay())->toBeTrue()
        ->and($package->eligibleVehicleIds())->toBe([$cabana->id]);
});

it('refuses tuk tuks or tuk tuk categories on a stay package', function (): void {
    $tukTuk = Vehicle::factory()->create();

    $this->actingAs($this->manager)
        ->post('/control-panel/packages', packagePayload([
            'vehicle_ids' => [$tukTuk->id],
            'category_ids' => [$tukTuk->category_id],
        ]))
        ->assertSessionHasErrors(['vehicle_ids', 'category_ids']);
});

it('shows where you will stay, with a map link, on a stay package page', function (): void {
    $cabana = Vehicle::factory()->stay()->create(['name' => ['en' => 'Sunset Cabana']]);
    $package = Package::factory()->create(['kind' => VehicleCategory::KIND_STAY, 'name' => ['en' => 'Surf & Stay'], 'is_active' => true]);
    $package->vehicles()->attach($cabana->id);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 25]);

    $this->get('/en/packages/'.$package->slugFor('en'))
        ->assertOk()
        ->assertSee("Where you'll stay")
        ->assertSee('Sunset Cabana')
        ->assertSee('Open in Google Maps')
        ->assertSee('/ night');
});
