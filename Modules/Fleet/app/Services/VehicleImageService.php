<?php

declare(strict_types=1);

namespace Modules\Fleet\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleImage;

/**
 * Brief §9: auto-resize on upload + WebP conversion, pure-PHP (GD driver —
 * no Imagick dependency, matches the small-VPS/shared-hosting constraint).
 * Stores two variants per upload: a full-size web image (max 1600px wide)
 * and a thumbnail (400px wide, "-thumb" suffix by convention) so the admin
 * list view doesn't have to pull full-size images.
 */
final class VehicleImageService
{
    private const int FULL_MAX_WIDTH = 1600;

    private const int THUMB_WIDTH = 400;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function store(Vehicle $vehicle, UploadedFile $file, bool $isPrimary = false): VehicleImage
    {
        $filename = Str::uuid()->toString();
        $directory = "vehicles/{$vehicle->id}";
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
            $vehicle->images()->update(['is_primary' => false]);
        }

        return $vehicle->images()->create([
            'path' => $fullPath,
            'is_primary' => $isPrimary || $vehicle->images()->doesntExist(),
            'sort_order' => $vehicle->images()->count(),
        ]);
    }

    public function delete(VehicleImage $image): void
    {
        $thumbPath = preg_replace('/\.webp$/', '-thumb.webp', $image->path);

        Storage::disk('public')->delete(array_filter([$image->path, $thumbPath]));

        $image->delete();
    }
}
