<?php

declare(strict_types=1);

namespace Modules\Pricing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pricing\Models\Currency;

class PricingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->updateOrCreate(
            ['code' => config('pricing.default_currency')],
            ['symbol' => '$', 'is_base' => true, 'is_active' => true, 'decimal_places' => 2],
        );
    }
}
