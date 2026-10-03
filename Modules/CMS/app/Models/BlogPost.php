<?php

declare(strict_types=1);

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Support\SanitizesRichText;
use Modules\Localization\Support\HasTranslatableSlug;
use Spatie\Translatable\HasTranslations;

/**
 * @property array<string,string> $title
 * @property array<string,string>|null $excerpt
 * @property array<string,string> $body
 * @property bool $is_published
 */
final class BlogPost extends Model
{
    /** @use HasFactory<\Modules\CMS\Database\Factories\BlogPostFactory> */
    use HasFactory, HasTranslatableSlug, HasTranslations, SanitizesRichText;

    public array $translatable = ['title', 'excerpt', 'body'];

    protected $fillable = [
        'title',
        'excerpt',
        'body',
        'cover_image',
        'author_id',
        'published_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    protected function slugSourceField(): string
    {
        return 'title';
    }

    protected static function booted(): void
    {
        static::saving(function (self $post): void {
            if ($post->isDirty('body')) {
                $post->setTranslations('body', $post->purifyTranslatableHtml($post->getTranslations('body')) ?? []);
            }
        });

        static::saved(fn (self $post) => $post->syncSlugs());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
