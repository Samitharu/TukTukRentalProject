<?php

declare(strict_types=1);

namespace Modules\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pricing\Models\Coupon;

/**
 * @extends Factory<Coupon>
 */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('????##')),
            'type' => Coupon::TYPE_PERCENT,
            'value' => fake()->numberBetween(5, 30),
            'is_active' => true,
        ];
    }
}
