<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff enter every price in USD and in EUR; neither is converted from the
 * other. Existing prices have no EUR yet, and customers see them in USD only
 * until staff add one. A free item is free in EUR too.
 */
return new class () extends Migration {
    /** @var array<string, array{0: string, 1: string}> table => [new column, column it follows] */
    private array $columns = [
        'package_pricing_tiers' => ['price_eur', 'price'],
        'addons' => ['price_eur', 'price'],
        'transfer_areas' => ['price_eur', 'price'],
        'packages' => ['extra_km_rate_eur', 'extra_km_rate'],
        'package_seasons' => ['price_modifier_value_eur', 'price_modifier_value'],
        'bookings' => ['extra_km_rate_eur', 'extra_km_rate'],
        'booking_addons' => ['unit_price_eur', 'unit_price'],
        'booking_extra_charges' => ['amount_eur', 'amount'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => [$column, $after]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $after): void {
                $blueprint->decimal($column, 10, 2)->nullable()->after($after);
            });
        }

        foreach (['package_pricing_tiers', 'addons', 'transfer_areas'] as $table) {
            DB::table($table)->where('price', 0)->update(['price_eur' => 0]);
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => [$column]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropColumn($column);
            });
        }
    }
};
