<?php

declare(strict_types=1);

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $flag_icon
 */
final class Locale extends Model
{
    /** @use HasFactory<\Modules\Localization\Database\Factories\LocaleFactory> */
    use HasFactory;

    public const string CACHE_KEY_ACTIVE = 'locales:active';

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_default',
        'is_active',
        'sort_order',
        'flag_icon',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Route by code (e.g. /control-panel/locales/de/edit) — friendlier than
     * a numeric id for the one admin screen an operator will actually look at.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY_ACTIVE));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY_ACTIVE));
    }

    /**
     * Active locales, ordered for display, cached indefinitely until the
     * next locale create/update/delete invalidates the key. This is the
     * list the locale-routing middleware and the language switcher both read.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function activeCached(): \Illuminate\Support\Collection
    {
        return Cache::rememberForever(
            self::CACHE_KEY_ACTIVE,
            fn () => self::query()->active()->orderBy('sort_order')->get(),
        );
    }

    public static function defaultCode(): string
    {
        return self::activeCached()->firstWhere('is_default', true)?->code
            ?? config('app.locale');
    }
}
