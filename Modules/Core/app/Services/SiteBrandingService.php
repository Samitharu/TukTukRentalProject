<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Core\Models\SiteSetting;

/**
 * Same resize/WebP-conversion approach as VehicleImageService/
 * PackageImageService (brief §9) — pure-PHP GD driver. Unlike those, each
 * upload here REPLACES the single current logo/hero image rather than
 * adding to a gallery, so the old file is deleted once the new one is
 * safely written.
 */
final class SiteBrandingService
{
    private const int LOGO_MAX_WIDTH = 480;

    private const int HERO_MAX_WIDTH = 1920;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function updateLogo(SiteSetting $settings, UploadedFile $file): void
    {
        $path = $this->storeResized($file, 'branding', self::LOGO_MAX_WIDTH);
        $this->replace($settings, 'logo_path', $path);
    }

    public function updateHeroImage(SiteSetting $settings, UploadedFile $file): void
    {
        $path = $this->storeResized($file, 'branding', self::HERO_MAX_WIDTH);
        $this->replace($settings, 'hero_image_path', $path);
    }

    public function removeLogo(SiteSetting $settings): void
    {
        $this->replace($settings, 'logo_path', null);
    }

    public function removeHeroImage(SiteSetting $settings): void
    {
        $this->replace($settings, 'hero_image_path', null);
    }

    private function storeResized(UploadedFile $file, string $directory, int $maxWidth): string
    {
        $path = "{$directory}/".Str::uuid()->toString().'.webp';

        $image = $this->manager->decode($file->getRealPath());
        $image->scaleDown(width: $maxWidth);
        $encoded = $image->encodeUsingFileExtension('webp', quality: 85, strip: true);

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    private function replace(SiteSetting $settings, string $column, ?string $newPath): void
    {
        $oldPath = $settings->{$column};

        $settings->update([$column => $newPath]);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
