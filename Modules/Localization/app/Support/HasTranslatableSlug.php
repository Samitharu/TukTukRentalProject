<?php

declare(strict_types=1);

namespace Modules\Localization\Support;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Modules\Localization\Models\Locale;
use Modules\Localization\Models\RouteSlug;

/**
 * Gives any translatable model a per-locale slug, resolved through the
 * single `route_slugs` table (docs/03-database-schema.md) rather than a
 * slug column per model — this is what lets `/de/tuk-tuk-mieten` and
 * `/en/rent-a-tuk-tuk` resolve to the same record with zero per-language
 * route definitions (docs/01-architecture.md §4).
 *
 * A model using this trait must define a translatable `name` (or override
 * `slugSourceField()`) and call `syncSlugs()` after save — most simply from
 * a `static::saved()` hook in `booted()`.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTranslatableSlug
{
    public function routeSlugs(): MorphMany
    {
        return $this->morphMany(RouteSlug::class, 'routable', 'model_type', 'model_id');
    }

    protected function slugSourceField(): string
    {
        return 'name';
    }

    public function slugFor(string $locale): ?string
    {
        return $this->relationLoaded('routeSlugs')
            ? $this->routeSlugs->firstWhere('locale', $locale)?->slug
            : RouteSlug::query()
                ->where('model_type', static::class)
                ->where('model_id', $this->getKey())
                ->where('locale', $locale)
                ->value('slug');
    }

    /**
     * Creates or refreshes this record's slug for every active locale that
     * has a non-empty translation of the source field. Safe to call
     * repeatedly — a slug is only regenerated when the source text for
     * that locale actually changed.
     */
    public function syncSlugs(): void
    {
        $field = $this->slugSourceField();

        foreach (Locale::activeCached() as $locale) {
            $text = $this->getTranslation($field, $locale->code, false);

            if ($text === null || $text === '') {
                continue;
            }

            $desired = Str::slug($text);
            $existing = RouteSlug::query()
                ->where('model_type', static::class)
                ->where('model_id', $this->getKey())
                ->where('locale', $locale->code)
                ->first();

            if ($existing !== null && Str::startsWith($existing->slug, $desired)) {
                continue; // already based on current text (may have a -2, -3 suffix)
            }

            RouteSlug::query()->updateOrCreate(
                ['model_type' => static::class, 'model_id' => $this->getKey(), 'locale' => $locale->code],
                ['slug' => $this->uniqueSlug($desired, $locale->code)],
            );
        }
    }

    private function uniqueSlug(string $base, string $locale): string
    {
        $slug = $base;
        $suffix = 2;

        while (
            RouteSlug::query()
                ->where('model_type', static::class)
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->where('model_id', '!=', $this->getKey())
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function findBySlug(string $locale, string $slug): ?static
    {
        $modelId = RouteSlug::query()
            ->where('model_type', static::class)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->value('model_id');

        return $modelId !== null ? static::query()->find($modelId) : null;
    }
}
