<?php

declare(strict_types=1);

namespace Modules\Fleet\Support;

use Illuminate\Support\Facades\DB;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Package\Models\ProductCategory;

/**
 * The business rents three kinds of vehicle: Tuk Tuk (the Tuk Tuk line),
 * SUV and Bike (the Vehicle Rent line). The early "Standard" / "Premium"
 * unit types were both tuk tuks, so they are merged into "Tuk Tuk": their
 * vehicles and package restrictions move over, then the empty types go.
 * Safe to run again, and on a live database.
 */
final class DefaultUnitTypes
{
    public const array TUK_TUK = ['en' => 'Tuk Tuk', 'de' => 'Tuk-Tuk', 'fr' => 'Tuk-tuk', 'ru' => 'Тук-тук'];

    public const array SUV = ['en' => 'SUV', 'de' => 'SUV', 'fr' => 'SUV', 'ru' => 'Внедорожник'];

    public const array BIKE = ['en' => 'Bike', 'de' => 'Motorrad', 'fr' => 'Moto', 'ru' => 'Мотоцикл'];

    public static function install(): void
    {
        $tukTukLine = self::line('Tuk Tuk Rental');
        $rentLine = self::line('Vehicle Rent');

        $tukTuk = self::named('Tuk Tuk') ?? self::named('Standard') ?? self::named('Premium');

        if ($tukTuk === null) {
            $tukTuk = VehicleCategory::query()->create(['kind' => VehicleCategory::KIND_VEHICLE, 'name' => self::TUK_TUK, 'sort_order' => 1, 'is_active' => true]);
        }

        $tukTuk->update([
            'name' => self::TUK_TUK,
            'product_category_id' => $tukTukLine ?? $tukTuk->product_category_id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        foreach (['Standard', 'Premium'] as $old) {
            $type = self::named($old);

            if ($type === null || $type->id === $tukTuk->id) {
                continue;
            }

            DB::table('vehicles')->where('category_id', $type->id)->update(['category_id' => $tukTuk->id]);

            // Packages restricted to the old type are restricted to Tuk Tuk instead.
            $packageIds = DB::table('package_categories')->where('category_id', $type->id)->pluck('package_id');
            $already = DB::table('package_categories')->where('category_id', $tukTuk->id)->whereIn('package_id', $packageIds)->pluck('package_id');
            DB::table('package_categories')->insert($packageIds->diff($already)->map(fn ($id) => ['package_id' => $id, 'category_id' => $tukTuk->id])->values()->all());
            DB::table('package_categories')->where('category_id', $type->id)->delete();

            $type->delete();
        }

        foreach ([[self::SUV, 2], [self::BIKE, 3]] as [$name, $order]) {
            $type = self::named($name['en'])
                ?? VehicleCategory::query()->create(['kind' => VehicleCategory::KIND_VEHICLE, 'name' => $name, 'sort_order' => $order, 'is_active' => true]);

            if ($type->product_category_id === null && $rentLine !== null) {
                $type->update(['product_category_id' => $rentLine]);
            }
        }
    }

    private static function named(string $englishName): ?VehicleCategory
    {
        return VehicleCategory::query()->where('kind', VehicleCategory::KIND_VEHICLE)->where('name->en', $englishName)->orderBy('id')->first();
    }

    private static function line(string $englishName): ?int
    {
        return ProductCategory::query()->where('name->en', $englishName)->value('id');
    }
}
