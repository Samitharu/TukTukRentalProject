<?php

declare(strict_types=1);

namespace Modules\Package\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Package\Models\ProductCategory;

/**
 * One cover photo per category, resized and converted to WebP the same
 * way as PackageImageService. Replacing it deletes the previous file.
 */
final class ProductCategoryImageService
{
    private const int MAX_WIDTH = 1600;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function replace(ProductCategory $category, UploadedFile $file): void
    {
        $path = "product-categories/{$category->id}/".Str::uuid()->toString().'.webp';

        $image = $this->manager->decode($file->getRealPath());
        $image->scaleDown(width: self::MAX_WIDTH);

        Storage::disk('public')->put($path, (string) $image->encodeUsingFileExtension('webp', quality: 82, strip: true));

        $this->delete($category);
        $category->update(['image_path' => $path]);
    }

    public function delete(ProductCategory $category): void
    {
        if ($category->image_path !== null) {
            Storage::disk('public')->delete($category->image_path);
            $category->update(['image_path' => null]);
        }
    }
}
