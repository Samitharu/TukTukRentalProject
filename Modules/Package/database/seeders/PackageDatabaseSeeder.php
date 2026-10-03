<?php

declare(strict_types=1);

namespace Modules\Package\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;

class PackageDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $helmet = Addon::query()->updateOrCreate(
            ['name->en' => 'Helmet'],
            ['name' => ['en' => 'Helmet'], 'price' => 0, 'pricing_unit' => Addon::UNIT_FLAT, 'max_quantity' => 2, 'is_active' => true],
        );

        $simCard = Addon::query()->updateOrCreate(
            ['name->en' => 'SIM Card'],
            ['name' => ['en' => 'SIM Card'], 'price' => 5, 'pricing_unit' => Addon::UNIT_FLAT, 'max_quantity' => 1, 'is_active' => true],
        );

        $package = Package::query()->updateOrCreate(
            ['name->en' => 'Island Explorer'],
            [
                'name' => ['en' => 'Island Explorer'],
                'description' => ['en' => 'Our most popular self-drive tuk tuk package, perfect for exploring at your own pace.'],
                'pricing_model' => Package::MODEL_TIERED,
                'min_days' => 1,
                'max_days' => null,
                'deposit_amount' => 30,
                'deposit_is_percent' => true,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
            ],
        );

        $package->pricingTiers()->updateOrCreate(['min_days' => 1, 'max_days' => 3], ['price' => 25]);
        $package->pricingTiers()->updateOrCreate(['min_days' => 4, 'max_days' => 7], ['price' => 20]);
        $package->pricingTiers()->updateOrCreate(['min_days' => 8, 'max_days' => null], ['price' => 15]);

        $standard = VehicleCategory::query()->where('name->en', 'Standard')->first();
        if ($standard !== null) {
            $package->categories()->syncWithoutDetaching([$standard->id]);
        }

        $package->addons()->syncWithoutDetaching([
            $helmet->id => ['is_included' => true],
            $simCard->id => ['is_included' => false],
        ]);
    }
}
