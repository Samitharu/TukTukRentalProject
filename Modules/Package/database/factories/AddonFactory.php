<?php

declare(strict_types=1);

namespace Modules\Package\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Package\Models\Addon;

/**
 * @extends Factory<Addon>
 */
final class AddonFactory extends Factory
{
    protected $model = Addon::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => fake()->randomElement(['Helmet', 'Phone Holder', 'SIM Card', 'Insurance Upgrade', 'Extra Driver'])],
            'price' => fake()->randomFloat(2, 2, 25),
            'pricing_unit' => fake()->randomElement([Addon::UNIT_FLAT, Addon::UNIT_PER_DAY]),
            'max_quantity' => 1,
            'is_active' => true,
        ];
    }
}
