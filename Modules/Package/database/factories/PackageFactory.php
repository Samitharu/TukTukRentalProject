<?php

declare(strict_types=1);

namespace Modules\Package\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * @extends Factory<Package>
 */
final class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            // The default category of the requested kind (the migration
            // creates one per kind), so `kind` overrides keep working.
            'product_category_id' => fn (array $attributes) => ProductCategory::query()
                ->where('kind', $attributes['kind'] ?? Package::KIND_VEHICLE)
                ->orderBy('sort_order')
                ->value('id'),
            'name' => ['en' => fake()->words(3, true).' Package'],
            'description' => ['en' => fake()->sentence()],
            'pricing_model' => Package::MODEL_TIERED,
            'min_days' => 1,
            'max_days' => null,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
