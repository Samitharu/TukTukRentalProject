<?php

declare(strict_types=1);

use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->line = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();
});

function homePackage(object $test, string $name, array $attributes = []): Package
{
    $package = Package::factory()->create([
        'name' => ['en' => $name],
        'product_category_id' => $test->line->id,
        'is_featured' => true,
        'is_active' => true,
        'pricing_model' => Package::MODEL_PER_DAY,
        'min_days' => 1,
        'max_days' => null,
        ...$attributes,
    ]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20, 'price_eur' => 18]);

    return $package;
}

it('shows every featured package on the homepage, in their sort order', function (): void {
    foreach (['Fourth Featured' => 4, 'First Featured' => 1, 'Third Featured' => 3, 'Second Featured' => 2, 'Fifth Featured' => 5] as $name => $order) {
        homePackage($this, $name, ['sort_order' => $order]);
    }

    $this->get('/en')->assertOk()
        ->assertSeeInOrder(['First Featured', 'Second Featured', 'Third Featured', 'Fourth Featured', 'Fifth Featured']);
});

it('leaves out packages that are not featured, switched off or expired', function (): void {
    homePackage($this, 'Shown Package');
    homePackage($this, 'Not Featured Package', ['is_featured' => false]);
    homePackage($this, 'Switched Off Package', ['is_active' => false]);
    homePackage($this, 'Expired Package', ['valid_from' => now()->subMonth()->toDateString(), 'valid_until' => now()->subDay()->toDateString()]);

    $this->get('/en')->assertOk()
        ->assertSee('Shown Package')
        ->assertDontSee('Not Featured Package')
        ->assertDontSee('Switched Off Package')
        ->assertDontSee('Expired Package');
});
