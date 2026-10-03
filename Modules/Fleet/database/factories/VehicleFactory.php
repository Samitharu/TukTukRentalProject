<?php

declare(strict_types=1);

namespace Modules\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;

/**
 * @extends Factory<Vehicle>
 */
final class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'category_id' => VehicleCategory::factory(),
            'name' => ['en' => 'Tuk Tuk '.fake()->bothify('??-###')],
            'plate_no' => mb_strtoupper(fake()->unique()->bothify('??-####')),
            'model' => fake()->randomElement(['Bajaj RE', 'TVS King', 'Piaggio Ape']),
            'year' => fake()->numberBetween(2018, 2026),
            'colour' => fake()->safeColorName(),
            'seats' => 3,
            'transmission' => 'manual',
            'fuel_type' => 'petrol',
            'features' => ['helmet_included', 'phone_holder'],
            'description' => ['en' => fake()->sentence()],
            'status' => Vehicle::STATUS_ACTIVE,
        ];
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Vehicle::STATUS_MAINTENANCE]);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Vehicle::STATUS_RETIRED]);
    }
}
