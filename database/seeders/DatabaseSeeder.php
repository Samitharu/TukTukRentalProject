<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Availability\Database\Seeders\AvailabilityDatabaseSeeder;
use Modules\CMS\Database\Seeders\CMSDatabaseSeeder;
use Modules\Fleet\Database\Seeders\FleetDatabaseSeeder;
use Modules\Localization\Database\Seeders\LocalizationDatabaseSeeder;
use Modules\Package\Database\Seeders\PackageDatabaseSeeder;
use Modules\Pricing\Database\Seeders\PricingDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocalizationDatabaseSeeder::class,
            // AdminDatabaseSeeder depends on Locale being seeded only
            // indirectly (none yet) but must run after Localization so
            // role/permission ordering in the demo data stays predictable.
            AdminDatabaseSeeder::class,
            PricingDatabaseSeeder::class,
            AvailabilityDatabaseSeeder::class,
            FleetDatabaseSeeder::class,
            // PackageDatabaseSeeder links to Fleet's "Standard" category —
            // must run after FleetDatabaseSeeder.
            PackageDatabaseSeeder::class,
            CMSDatabaseSeeder::class,
        ]);
    }
}
