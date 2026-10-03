<?php

declare(strict_types=1);

namespace Modules\Availability\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Availability\Models\BusinessLocation;

class AvailabilityDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        BusinessLocation::query()->updateOrCreate(
            ['name->en' => 'Main Office'],
            [
                'name' => ['en' => 'Main Office'],
                'address' => 'TBD — pending business address (see docs/01-architecture.md §7)',
                'is_pickup_point' => true,
                'is_active' => true,
            ],
        );
    }
}
