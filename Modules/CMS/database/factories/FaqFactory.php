<?php

declare(strict_types=1);

namespace Modules\CMS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CMS\Models\Faq;

/**
 * @extends Factory<Faq>
 */
final class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'question' => ['en' => fake()->sentence().'?'],
            'answer' => ['en' => fake()->paragraph()],
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
