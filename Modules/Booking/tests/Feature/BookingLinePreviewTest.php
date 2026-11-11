<?php

declare(strict_types=1);

use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * The dates step shows what the chosen service offers before any dates are
 * picked, and says so plainly when it has nothing to book yet.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
});

it('lists the chosen service\'s packages on the dates step', function (): void {
    $line = ProductCategory::query()->create(['kind' => Package::KIND_TOUR, 'name' => ['en' => 'Tour Plan'], 'is_active' => true]);
    $tour = Package::factory()->create(['name' => ['en' => 'Hill Country Tour'], 'product_category_id' => $line->id, 'pricing_model' => Package::MODEL_PER_DAY, 'min_days' => 2, 'max_days' => null]);
    $tour->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 45]);

    $this->get('/en/booking/start?line='.$line->id)
        ->assertOk()
        ->assertSee('Available Tour Plan packages')
        ->assertSee('Hill Country Tour')
        ->assertSee('name="start_date"', false);
});

it('says the service has no packages yet instead of asking for dates', function (): void {
    $line = ProductCategory::query()->create(['kind' => Package::KIND_TOUR, 'name' => ['en' => 'Tour Plan'], 'is_active' => true]);

    $this->get('/en/booking/start?line='.$line->id)
        ->assertOk()
        ->assertSee('No Tour Plan packages at the moment')
        ->assertDontSee('name="start_date"', false);
});
