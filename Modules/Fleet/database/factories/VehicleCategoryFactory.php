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

    /** A category of cabanas/rooms, booked by the night. */
    public function stay(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => VehicleCategory::KIND_STAY,
            'name' => ['en' => fake()->randomElement(['Beach Cabanas', 'Garden Rooms', 'Surf Huts'])],
        ]);
    }
}
