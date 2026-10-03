<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string|null $logo_path
 * @property string|null $hero_image_path
 */
final class SiteSetting extends Model
{
    private const string CACHE_KEY = 'site_settings:current';

    protected $fillable = [
        'logo_path',
        'hero_image_path',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Always exactly one row — created on first access rather than via a
     * seeder, since there's nothing meaningful to seed (both images start
     * unset until an admin uploads one).
     */
    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->firstOrCreate([]));
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    public function heroImageUrl(): ?string
    {
        return $this->hero_image_path ? asset('storage/'.$this->hero_image_path) : null;
    }
}
