<?php

declare(strict_types=1);

namespace Modules\CMS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CMS\Models\BlogPost;

/**
 * @extends Factory<BlogPost>
 */
final class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        return [
            'title' => ['en' => fake()->sentence(5)],
            'excerpt' => ['en' => fake()->sentence(12)],
            'body' => ['en' => '<p>'.fake()->paragraphs(3, true).'</p>'],
            'is_published' => true,
            'published_at' => now(),
        ];
    }
}
