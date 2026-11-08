<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Modules\Pricing\Services\PricingService;

/**
 * An hourly package with a minimum of several hours shows the price of that
 * minimum ("from $12.00 for 2 hours"), not the hourly rate, wherever customers
 * see it.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->line = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();
});

/** @param array<int, array{0: int, 1: int|null, 2: float, 3: float}> $tiers min, max, USD, EUR */
function hourlyPackage(object $test, string $name, int $minHours, array $tiers = [[1, null, 6, 5]]): Package
{
    $package = Package::factory()->create([
        'name' => ['en' => $name],
        'product_category_id' => $test->line->id,
        'is_featured' => true,
        'is_active' => true,
        'pricing_model' => Package::MODEL_PER_HOUR,
        'min_days' => 1,
        'max_days' => 1,
        'min_hours' => $minHours,
        'max_hours' => 8,
    ]);

    foreach ($tiers as [$min, $max, $usd, $eur]) {
        $package->pricingTiers()->create(['min_days' => $min, 'max_days' => $max, 'price' => $usd, 'price_eur' => $eur]);
    }

    return $package->load('pricingTiers');
}

it('shows the price of the minimum hours on the homepage and packages page', function (string $page): void {
    hourlyPackage($this, 'City Hop', 2);

    $this->get($page)->assertOk()
        ->assertSee('City Hop')
        ->assertSee('$12.00 / €10.00')
        ->assertSee('for 2 hours')
        ->assertDontSee('$6.00 / €5.00');
})->with(['homepage' => ['/en'], 'packages page' => ['/en/packages']]);

it('keeps showing the hourly rate when the minimum is one hour', function (): void {
    hourlyPackage($this, 'Quick Hop', 1);

    $this->get('/en')->assertOk()
        ->assertSee('$6.00 / €5.00')
        ->assertSee('/ hour')
        ->assertDontSee('for 1 hours');
});

it('uses the cheaper rate for longer bookings in the price table, and still states the hourly rate', function (): void {
    // 1–3 hours at $8, 4 hours or more at $6, at least 2 hours.
    $package = hourlyPackage($this, 'Island Hours', 2, [[1, 3, 8, 7], [4, null, 6, 5]]);

    expect($package->tierPrice($package->fromTier()))->toBe('$16.00 / €14.00');

    $this->get('/en/packages/'.$package->slugFor('en'))->assertOk()
        ->assertSeeInOrder(['$16.00 / €14.00', 'for 2 hours', '$8.00 / €7.00', '/ hour'])
        ->assertSeeInOrder(['$24.00 / €20.00', 'for 4 hours', '$6.00 / €5.00', '/ hour'])
        ->assertSee('"lowPrice":16', false);
});

it('shows the same amount the booking will charge for the minimum hours', function (): void {
    $package = hourlyPackage($this, 'City Hop', 3);

    $price = app(PricingService::class)->calculate($package, CarbonImmutable::today()->addDays(5), 1, hours: 3);

    expect($package->tierPrice($package->fromTier()))->toBe('$'.number_format($price->total, 2).' / €'.number_format($price->eur['total'], 2));
});

it('leaves packages priced by the day as they were', function (): void {
    $package = Package::factory()->create([
        'name' => ['en' => 'Day Rental'],
        'product_category_id' => $this->line->id,
        'is_featured' => true,
        'pricing_model' => Package::MODEL_PER_DAY,
        'min_days' => 2,
        'max_days' => null,
    ]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 16, 'price_eur' => 14]);

    $this->get('/en')->assertOk()->assertSee('$16.00 / €14.00')->assertSee('/ day');
});
