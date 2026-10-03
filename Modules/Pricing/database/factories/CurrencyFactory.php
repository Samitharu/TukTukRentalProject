<?php

declare(strict_types=1);

namespace Modules\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pricing\Models\Currency;

/**
 * @extends Factory<Currency>
 */
final class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->currencyCode(),
            'symbol' => '$',
            'is_base' => false,
            'is_active' => true,
            'decimal_places' => 2,
        ];
    }
}
