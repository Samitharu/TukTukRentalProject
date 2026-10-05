<?php

declare(strict_types=1);

namespace Modules\Package\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * @extends Factory<ProductCategory>
 */
final class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'kind' => Package::KIND_VEHICLE,
            'name' => ['en' => fake()->unique()->words(2, true)],
            'description' => ['en' => fake()->sentence()],
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function activity(): self
    {
        return $this->state(['kind' => Package::KIND_ACTIVITY]);
    }

    public function stay(): self
    {
        return $this->state(['kind' => Package::KIND_STAY]);
    }
}
