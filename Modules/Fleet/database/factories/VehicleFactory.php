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

    /** A cabana/room at its own location — no plate, gearbox or fuel. */
    public function stay(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => VehicleCategory::factory()->stay(),
            'name' => ['en' => 'Cabana '.fake()->unique()->numberBetween(1, 999)],
            'plate_no' => null,
            'model' => null,
            'year' => null,
            'colour' => null,
            'seats' => 2,
            'transmission' => null,
            'fuel_type' => null,
            'features' => ['wifi', 'sea_view'],
            'address' => 'Main Point, Arugam Bay',
            'google_maps_url' => 'https://www.google.com/maps/place/Arugam+Bay/@6.8406,81.8368,17z',
            'lat' => 6.8406,
            'lng' => 81.8368,
        ]);
    }
}
