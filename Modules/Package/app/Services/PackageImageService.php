<?php

declare(strict_types=1);

namespace Modules\Package\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageImage;

/**
 * Same auto-resize + WebP-conversion approach as
 * Modules\Fleet\Services\VehicleImageService (brief §9) — pure-PHP GD
 * driver, no Imagick, two variants per upload (full + thumbnail).
 */
final class PackageImageService
{
    private const int FULL_MAX_WIDTH = 1600;

    private const int THUMB_WIDTH = 400;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function store(Package $package, UploadedFile $file, bool $isPrimary = false): PackageImage
    {
        $filename = Str::uuid()->toString();
        $directory = "packages/{$package->id}";
        $fullPath = "{$directory}/{$filename}.webp";
        $thumbPath = "{$directory}/{$filename}-thumb.webp";

        $image = $this->manager->decode($file->getRealPath());
        $image->scaleDown(width: self::FULL_MAX_WIDTH);
        $fullEncoded = $image->encodeUsingFileExtension('webp', quality: 82, strip: true);

        $thumb = $this->manager->decode($file->getRealPath());
        $thumb->cover(self::THUMB_WIDTH, self::THUMB_WIDTH);
        $thumbEncoded = $thumb->encodeUsingFileExtension('webp', quality: 75, strip: true);

        Storage::disk('public')->put($fullPath, (string) $fullEncoded);
        Storage::disk('public')->put($thumbPath, (string) $thumbEncoded);

        if ($isPrimary) {
            $package->images()->update(['is_primary' => false]);
        }

        return $package->images()->create([
            'path' => $fullPath,
            'is_primary' => $isPrimary || $package->images()->doesntExist(),
            'sort_order' => $package->images()->count(),
        ]);
    }

    public function delete(PackageImage $image): void
    {
        $thumbPath = preg_replace('/\.webp$/', '-thumb.webp', $image->path);

        Storage::disk('public')->delete(array_filter([$image->path, $thumbPath]));

        $image->delete();
    }
}
