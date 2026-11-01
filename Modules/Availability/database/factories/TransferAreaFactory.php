<?php

declare(strict_types=1);

namespace Modules\Availability\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Availability\Models\TransferArea;

/**
 * @extends Factory<TransferArea>
 */
final class TransferAreaFactory extends Factory
{
    protected $model = TransferArea::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => fake()->city()],
            'price' => 20,
            'duration_minutes' => 120,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
