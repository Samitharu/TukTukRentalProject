<?php

declare(strict_types=1);

namespace Modules\CMS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CMS\Models\Page;

/**
 * @extends Factory<Page>
 */
final class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'template' => 'default',
            'title' => ['en' => fake()->sentence(3)],
            'content' => ['en' => '<p>'.fake()->paragraph().'</p>'],
            'is_published' => true,
        ];
    }
}
