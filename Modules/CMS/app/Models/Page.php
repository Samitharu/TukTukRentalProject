<?php

declare(strict_types=1);

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\SanitizesRichText;
use Modules\Localization\Support\HasTranslatableSlug;
use Spatie\Translatable\HasTranslations;

/**
 * Generic content page — About, Terms, Privacy Policy, Cancellation
 * Policy, How It Works, Driving Permit Guide, etc. all use this one model
 * rather than a dedicated model per page, since they share the same shape
 * (a translatable title + rich-text body) and the admin should be able to
 * add a new static page without a code change.
 *
 * @property int $id
 * @property string $template
 * @property array<string,string> $title
 * @property array<string,string>|null $content
 * @property bool $is_published
 */
final class Page extends Model
{
    /** @use HasFactory<\Modules\CMS\Database\Factories\PageFactory> */
    use HasFactory, HasTranslatableSlug, HasTranslations, SanitizesRichText;

    public array $translatable = ['title', 'content'];

    protected $fillable = [
        'template',
        'title',
        'content',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected function slugSourceField(): string
    {
        return 'title';
    }

    protected static function booted(): void
    {
        static::saving(function (self $page): void {
            if ($page->isDirty('content')) {
                // Read/write the raw per-locale array explicitly — the
                // magic `$page->content` accessor returns only the current
                // locale's string, which would silently drop every other
                // language's content here.
                $page->setTranslations('content', $page->purifyTranslatableHtml($page->getTranslations('content')) ?? []);
            }
        });

        static::saved(fn (self $page) => $page->syncSlugs());
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
