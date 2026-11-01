<?php

declare(strict_types=1);

namespace Modules\Fleet\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Fleet\Support\DefaultUnitTypes;

/**
 * Modest local-dev/demo dataset: the business's three vehicle types
 * (Tuk Tuk, SUV, Bike) and a few tuk tuks to book.
 */
class FleetDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DefaultUnitTypes::install();

        $tukTuk = VehicleCategory::query()->where('name->en', DefaultUnitTypes::TUK_TUK['en'])->firstOrFail();

        foreach (['WP-CAB-1001', 'WP-CAB-1002', 'WP-CAB-1003', 'WP-CAB-2001', 'WP-CAB-2002'] as $index => $plate) {
            Vehicle::query()->updateOrCreate(
                ['plate_no' => $plate],
                [
                    'category_id' => $tukTuk->id,
                    'name' => ['en' => 'Tuk Tuk '.($index + 1)],
                    'model' => 'Bajaj RE',
                    'year' => 2023,
                    'colour' => 'Yellow',
                    'seats' => 3,
                    'transmission' => 'manual',
                    'fuel_type' => 'petrol',
                    'features' => ['helmet_included', 'phone_holder'],
                    'status' => Vehicle::STATUS_ACTIVE,
                ],
            );
        }
    }
}
