<?php

declare(strict_types=1);

namespace Modules\Localization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Localization\Models\Locale;

/**
 * @extends Factory<Locale>
 */
final class LocaleFactory extends Factory
{
    protected $model = Locale::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->languageCode(),
            'name' => fake()->word(),
            'native_name' => fake()->word(),
            'is_default' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
