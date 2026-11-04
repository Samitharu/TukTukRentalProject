<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Availability\Models\DeliveryZone;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Pricing\Models\Currency;
use Modules\Pricing\Services\PricingService;

beforeEach(function (): void {
    Currency::query()->create(['code' => 'USD', 'symbol' => '$', 'is_base' => true, 'is_active' => true, 'decimal_places' => 2]);
    $this->service = new PricingService();
});

function makeTieredPackage(): Package
{
    $package = Package::factory()->create(['pricing_model' => Package::MODEL_TIERED]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => 3, 'price' => 25]);
    $package->pricingTiers()->create(['min_days' => 4, 'max_days' => 7, 'price' => 20]);
    $package->pricingTiers()->create(['min_days' => 8, 'max_days' => null, 'price' => 15]);

    return $package;
}

it('prices a tiered package using the matching bracket\'s daily rate', function (): void {
    $package = makeTieredPackage();

    $twoDay = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 2);
    expect($twoDay->baseAmount)->toBe(50.0); // 25/day * 2 days, tier 1-3

    $fiveDay = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 5);
    expect($fiveDay->baseAmount)->toBe(100.0); // 20/day * 5 days, tier 4-7

    $tenDay = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 10);
    expect($tenDay->baseAmount)->toBe(150.0); // 15/day * 10 days, tier 8+
});

it('prices a fixed-bundle package as a flat amount regardless of exact days in range', function (): void {
    $package = Package::factory()->create(['pricing_model' => Package::MODEL_FIXED_BUNDLE, 'min_days' => 3, 'max_days' => 5]);
    $package->pricingTiers()->create(['min_days' => 3, 'max_days' => 5, 'price' => 99]);

    $breakdown = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 4);

    expect($breakdown->baseAmount)->toBe(99.0);
});

it('applies a percentage seasonal adjustment only when the pickup weekday is included', function (): void {
    $package = makeTieredPackage();
    // 2026-06-01 is a Monday.
    $package->seasons()->create([
        'name' => 'Weekend Surcharge',
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-30',
        'price_modifier_type' => 'percent',
        'price_modifier_value' => 20,
        'weekday_mask' => (1 << 5) | (1 << 6), // Sat + Sun only
        'priority' => 0,
    ]);

    $mondayPickup = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 2);
    expect($mondayPickup->seasonalAdjustment)->toBe(0.0);

    $saturdayPickup = $this->service->calculate($package, CarbonImmutable::parse('2026-06-06'), 2);
    expect($saturdayPickup->seasonalAdjustment)->toBe(10.0); // 20% of 50
});

it('sums selected add-ons respecting per-day vs flat pricing and offered restriction', function (): void {
    $package = makeTieredPackage();

    $helmet = Addon::factory()->create(['price' => 5, 'pricing_unit' => Addon::UNIT_FLAT, 'max_quantity' => 2]);
    $sim = Addon::factory()->create(['price' => 3, 'pricing_unit' => Addon::UNIT_PER_DAY, 'max_quantity' => 1]);
    $notOffered = Addon::factory()->create(['price' => 100, 'pricing_unit' => Addon::UNIT_FLAT]);

    $package->addons()->attach([$helmet->id => ['is_included' => false], $sim->id => ['is_included' => false]]);

    $breakdown = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 2, [
        $helmet->id => 2,
        $sim->id => 1,
        $notOffered->id => 1, // must be silently ignored — not offered on this package
    ]);

    // helmet: 5 * 2 = 10 (flat * quantity); sim: 3/day * 2 days = 6
    expect($breakdown->addonsTotal)->toBe(16.0)
        ->and($breakdown->addonLines)->toHaveCount(2);
});

it('adds the delivery fee to the total', function (): void {
    $package = makeTieredPackage();
    $zone = DeliveryZone::factory()->create(['extra_fee' => 20]);

    $breakdown = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 2, [], $zone);

    expect($breakdown->total)->toBe(70.0);
});

it('forces the deposit to zero while online payment is disabled, regardless of package config', function (): void {
    config(['pricing.online_payment_enabled' => false]);

    $package = makeTieredPackage();
    $package->update(['deposit_amount' => 30, 'deposit_is_percent' => true]);

    $breakdown = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 2);

    expect($breakdown->depositAmount)->toBe(0.0);
});

it('computes a percentage deposit capped at the total once online payment is enabled', function (): void {
    config(['pricing.online_payment_enabled' => true]);

    $package = makeTieredPackage();
    $package->update(['deposit_amount' => 30, 'deposit_is_percent' => true]);

    $breakdown = $this->service->calculate($package, CarbonImmutable::parse('2026-06-01'), 2);

    expect($breakdown->depositAmount)->toBe(15.0) // 30% of 50
        ->and($breakdown->balanceDue())->toBe(35.0);
});
