<?php

declare(strict_types=1);

namespace Modules\Fleet\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;

/**
 * Modest local-dev/demo dataset — the full "10 tuk tuks, 6 packages, all
 * 4 languages" realistic demo data set is a Phase 9 deliverable. This just
 * gives Phase 3's admin screens and the Pricing/Availability services
 * something real to operate on while building/testing.
 */
class FleetDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $standard = VehicleCategory::query()->updateOrCreate(
            ['name->en' => 'Standard'],
            ['name' => ['en' => 'Standard'], 'description' => ['en' => 'Reliable everyday tuk tuks.'], 'sort_order' => 1, 'is_active' => true],
        );

        $premium = VehicleCategory::query()->updateOrCreate(
            ['name->en' => 'Premium'],
            ['name' => ['en' => 'Premium'], 'description' => ['en' => 'Newer, better-equipped tuk tuks.'], 'sort_order' => 2, 'is_active' => true],
        );

        $vehicles = [
            ['plate_no' => 'WP-CAB-1001', 'category_id' => $standard->id, 'name' => 'Standard Tuk Tuk 1'],
            ['plate_no' => 'WP-CAB-1002', 'category_id' => $standard->id, 'name' => 'Standard Tuk Tuk 2'],
            ['plate_no' => 'WP-CAB-1003', 'category_id' => $standard->id, 'name' => 'Standard Tuk Tuk 3'],
            ['plate_no' => 'WP-CAB-2001', 'category_id' => $premium->id, 'name' => 'Premium Tuk Tuk 1'],
            ['plate_no' => 'WP-CAB-2002', 'category_id' => $premium->id, 'name' => 'Premium Tuk Tuk 2'],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::query()->updateOrCreate(
                ['plate_no' => $vehicle['plate_no']],
                [
                    'category_id' => $vehicle['category_id'],
                    'name' => ['en' => $vehicle['name']],
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
