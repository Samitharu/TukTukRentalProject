<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table): void {
            // 'transfer': airport cars/vans, booked by time slot, never by the day.
            $table->enum('kind', ['vehicle', 'stay', 'transfer'])->default('vehicle')->change();

            // The product line (Tuk Tuk, Vehicle Rent, Airport Pickup…) a unit
            // type belongs to. An unrestricted package only auto-assigns units
            // of its own line, so a tuk tuk package never hands out a scooter.
            $table->foreignId('product_category_id')->nullable()->after('kind')
                ->constrained('product_categories')->nullOnDelete();
        });

        // Every existing unit type goes into the first line of its kind:
        // tuk tuks into Tuk Tuk Rental, cabanas and rooms into Stays.
        foreach (['vehicle', 'stay'] as $kind) {
            $lineId = DB::table('product_categories')->where('kind', $kind)->orderBy('sort_order')->orderBy('id')->value('id');

            if ($lineId !== null) {
                DB::table('vehicle_categories')
                    ->where('kind', $kind)
                    ->whereNull('product_category_id')
                    ->update(['product_category_id' => $lineId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_category_id');
        });
    }
};
