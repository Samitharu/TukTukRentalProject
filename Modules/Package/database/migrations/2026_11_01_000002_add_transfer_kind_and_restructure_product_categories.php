<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Package\Support\DefaultProductCategories;

return new class () extends Migration {
    public function up(): void
    {
        // 'transfer': airport pickups/drop-offs, booked online by time slot.
        Schema::table('packages', function (Blueprint $table): void {
            $table->enum('kind', ['vehicle', 'stay', 'activity', 'transfer'])->default('vehicle')->change();
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->enum('kind', ['vehicle', 'stay', 'activity', 'transfer'])->default('vehicle')->change();
        });

        // Tuk Tuk → Vehicle Rent → Airport Pickup → Stays → Kite & Surfing
        // (Surfing and Kitesurfing merged). Safe on a live database.
        DefaultProductCategories::restructure();
    }

    public function down(): void
    {
        // The enum values and merged categories are left in place: rolling
        // back must not orphan transfer packages or re-split surf packages.
    }
};
