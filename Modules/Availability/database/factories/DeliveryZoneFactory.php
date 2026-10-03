<?php

declare(strict_types=1);

namespace Modules\Availability\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Availability\Models\DeliveryZone;

/**
 * @extends Factory<DeliveryZone>
 */
final class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => fake()->citySuffix().' Zone'],
            'extra_fee' => fake()->randomFloat(2, 5, 30),
            'is_active' => true,
        ];
    }
}
