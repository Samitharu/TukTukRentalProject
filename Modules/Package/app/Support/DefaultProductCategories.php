<?php

declare(strict_types=1);

namespace Modules\Package\Support;

use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * The business's four starting categories, and the move of any package
 * that has none yet into the one matching its kind. Run by the migration
 * that introduced categories (so existing packages are never left
 * uncategorised) and by the seeder; safe to run again — it only creates
 * the defaults into an empty table, and only touches uncategorised packages.
 */
final class DefaultProductCategories
{
    /** @var array<int, array{kind: string, name: array<string, string>}> */
    private const array DEFAULTS = [
        ['kind' => Package::KIND_VEHICLE, 'name' => ['en' => 'Tuk Tuk Rental', 'de' => 'Tuk-Tuk-Vermietung', 'fr' => 'Location de tuk-tuk', 'ru' => 'Аренда тук-тука']],
        ['kind' => Package::KIND_STAY, 'name' => ['en' => 'Stays', 'de' => 'Unterkünfte', 'fr' => 'Hébergements', 'ru' => 'Проживание']],
        ['kind' => Package::KIND_ACTIVITY, 'name' => ['en' => 'Surfing', 'de' => 'Surfen', 'fr' => 'Surf', 'ru' => 'Серфинг']],
        ['kind' => Package::KIND_ACTIVITY, 'name' => ['en' => 'Kitesurfing', 'de' => 'Kitesurfen', 'fr' => 'Kitesurf', 'ru' => 'Кайтсерфинг']],
    ];

    public static function install(): void
    {
        if (ProductCategory::query()->doesntExist()) {
            foreach (self::DEFAULTS as $index => $category) {
                ProductCategory::query()->create([...$category, 'sort_order' => $index + 1, 'is_active' => true]);
            }
        }

        foreach ([Package::KIND_VEHICLE, Package::KIND_STAY, Package::KIND_ACTIVITY] as $kind) {
            $categoryId = ProductCategory::query()->where('kind', $kind)->orderBy('sort_order')->orderBy('id')->value('id');

            if ($categoryId !== null) {
                Package::withTrashed()
                    ->whereNull('product_category_id')
                    ->where('kind', $kind)
                    ->update(['product_category_id' => $categoryId]);
            }
        }
    }
}
