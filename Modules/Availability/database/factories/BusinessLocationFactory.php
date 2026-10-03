<?php

declare(strict_types=1);

namespace Modules\Availability\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Availability\Models\BusinessLocation;

/**
 * @extends Factory<BusinessLocation>
 */
final class BusinessLocationFactory extends Factory
{
    protected $model = BusinessLocation::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => fake()->city().' Office'],
            'address' => fake()->address(),
            'is_pickup_point' => true,
            'is_active' => true,
        ];
    }
}
