<?php

declare(strict_types=1);

namespace Modules\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fleet\Models\VehicleCategory;

/**
 * @extends Factory<VehicleCategory>
 */
final class VehicleCategoryFactory extends Factory
{
    protected $model = VehicleCategory::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Standard', 'Premium', 'Family', 'Off-Road']);

        return [
            'name' => ['en' => $name],
            'description' => ['en' => fake()->sentence()],
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
