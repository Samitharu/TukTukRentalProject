<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string|null $logo_path
 * @property string|null $hero_image_path
 * @property string|null $tripadvisor_url
 * @property string|null $footer_map_url
 */
final class SiteSetting extends Model
{
    private const string CACHE_KEY = 'site_settings:current';

    protected $fillable = [
        'logo_path',
        'hero_image_path',
        'tripadvisor_url',
        'footer_map_url',
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

    /**
     * The logo embedded in the markup, for PDFs: DomPDF does not fetch images
     * over the network. Sized to fit the box whatever the logo's shape. Null
     * when there is no logo or its file has gone or is unreadable.
     *
     * @return array{src: string, width: int, height: int}|null
     */
    public function logoForPdf(int $maxWidth, int $maxHeight): ?array
    {
        $disk = Storage::disk('public');

        if (! $this->logo_path || ! $disk->exists($this->logo_path)) {
            return null;
        }

        $contents = (string) $disk->get($this->logo_path);
        $size = @getimagesizefromstring($contents);

        if ($size === false) {
            return null;
        }

        $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);

        return [
            'src' => 'data:'.$size['mime'].';base64,'.base64_encode($contents),
            'width' => (int) round($size[0] * $scale),
            'height' => (int) round($size[1] * $scale),
        ];
    }

    public function heroImageUrl(): ?string
    {
        return $this->hero_image_path ? asset('storage/'.$this->hero_image_path) : null;
    }
}
