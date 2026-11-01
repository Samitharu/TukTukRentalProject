<?php

declare(strict_types=1);

namespace Modules\Package\Support;

use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * The business's product lines, and the move of any package that has
 * none yet into the one matching its kind.
 *
 * install() creates the original four (Tuk Tuk Rental, Stays, Surfing,
 * Kitesurfing) into an empty table — it runs from the migration that
 * introduced categories, before the 'transfer' kind existed, so it must
 * stay limited to those kinds. restructure() then brings any database —
 * fresh or live — to the current five lines. Both are safe to run again.
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

    private const array VEHICLE_RENT = ['en' => 'Vehicle Rent', 'de' => 'Fahrzeugvermietung', 'fr' => 'Location de véhicules', 'ru' => 'Аренда транспорта'];

    private const array AIRPORT_PICKUP = ['en' => 'Airport Pickup', 'de' => 'Flughafentransfer', 'fr' => 'Transfert aéroport', 'ru' => 'Трансфер из аэропорта'];

    private const array KITE_AND_SURFING = ['en' => 'Kite & Surfing', 'de' => 'Kite & Surfen', 'fr' => 'Kite & Surf', 'ru' => 'Кайт и серфинг'];

    public static function install(): void
    {
        if (ProductCategory::query()->doesntExist()) {
            foreach (self::DEFAULTS as $index => $category) {
                ProductCategory::query()->create([...$category, 'sort_order' => $index + 1, 'is_active' => true]);
            }
        }

        self::assignUncategorisedPackages();
    }

    /**
     * The five lines, in website order: Tuk Tuk → Vehicle Rent → Airport
     * Pickup → Stays → Kite & Surfing. Matches existing rows by their
     * English name (falling back to the first row of the same kind), adds
     * the missing ones, merges Kitesurfing into Surfing, and moves any
     * category the admin added themselves after these five. Never deletes
     * a category that still has packages.
     */
    public static function restructure(): void
    {
        $tukTuk = self::named('Tuk Tuk Rental')
            ?? self::firstOfKind(Package::KIND_VEHICLE, except: [self::VEHICLE_RENT['en']])
            ?? ProductCategory::query()->create(['kind' => Package::KIND_VEHICLE, 'name' => self::DEFAULTS[0]['name'], 'is_active' => true]);

        $vehicleRent = self::named(self::VEHICLE_RENT['en'])
            ?? ProductCategory::query()->create(['kind' => Package::KIND_VEHICLE, 'name' => self::VEHICLE_RENT, 'is_active' => true]);

        $airport = self::named(self::AIRPORT_PICKUP['en'])
            ?? ProductCategory::query()->create(['kind' => Package::KIND_TRANSFER, 'name' => self::AIRPORT_PICKUP, 'is_active' => true]);

        $stays = self::named('Stays')
            ?? self::firstOfKind(Package::KIND_STAY)
            ?? ProductCategory::query()->create(['kind' => Package::KIND_STAY, 'name' => self::DEFAULTS[1]['name'], 'is_active' => true]);

        $surf = self::named(self::KITE_AND_SURFING['en']) ?? self::named('Surfing') ?? self::named('Kitesurfing');

        if ($surf === null) {
            $surf = ProductCategory::query()->create(['kind' => Package::KIND_ACTIVITY, 'name' => self::KITE_AND_SURFING, 'is_active' => true]);
        } elseif ($surf->getTranslation('name', 'en', false) !== self::KITE_AND_SURFING['en']) {
            $surf->update(['name' => self::KITE_AND_SURFING]);
        }

        foreach (['Surfing', 'Kitesurfing'] as $merged) {
            $old = self::named($merged);

            if ($old !== null && $old->id !== $surf->id) {
                Package::withTrashed()->where('product_category_id', $old->id)->update(['product_category_id' => $surf->id, 'kind' => Package::KIND_ACTIVITY]);
                $old->delete();
            }
        }

        $lines = [$tukTuk, $vehicleRent, $airport, $stays, $surf];

        foreach ($lines as $index => $line) {
            $line->update(['sort_order' => $index + 1]);
        }

        ProductCategory::query()
            ->whereNotIn('id', array_map(fn (ProductCategory $line) => $line->id, $lines))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(fn (ProductCategory $other, int $index) => $other->update(['sort_order' => count($lines) + 1 + $index]));

        self::assignUncategorisedPackages();
    }

    private static function assignUncategorisedPackages(): void
    {
        foreach (Package::KINDS as $kind) {
            $categoryId = ProductCategory::query()->where('kind', $kind)->orderBy('sort_order')->orderBy('id')->value('id');

            if ($categoryId !== null) {
                Package::withTrashed()
                    ->whereNull('product_category_id')
                    ->where('kind', $kind)
                    ->update(['product_category_id' => $categoryId]);
            }
        }
    }

    private static function named(string $englishName): ?ProductCategory
    {
        return ProductCategory::query()->where('name->en', $englishName)->orderBy('id')->first();
    }

    /** @param  string[]  $except  English names to skip */
    private static function firstOfKind(string $kind, array $except = []): ?ProductCategory
    {
        return ProductCategory::query()
            ->where('kind', $kind)
            ->when($except !== [], fn ($q) => $q->whereNotIn('name->en', $except))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
