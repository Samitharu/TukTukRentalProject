<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Package\Support\DefaultProductCategories;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            // 'activity' (surfing, kitesurfing…): listed with prices, booked
            // by message for now — no unit is ever auto-assigned to one.
            $table->enum('kind', ['vehicle', 'stay', 'activity'])->default('vehicle')->change();
            $table->enum('pricing_model', ['per_day', 'per_week', 'per_month', 'fixed_bundle', 'tiered', 'per_hour', 'per_person'])
                ->default('per_day')
                ->change();

            $table->foreignId('product_category_id')->nullable()->after('kind')
                ->constrained('product_categories')->nullOnDelete();

            // Hourly packages: the length a customer may pick. The tuk tuk
            // is still reserved for the whole day (day-granular slots).
            $table->unsignedTinyInteger('min_hours')->nullable()->after('max_days');
            $table->unsignedTinyInteger('max_hours')->nullable()->after('min_hours');

            // Distance: included_km already existed (per day); it can now be
            // a total for the whole booking instead, and km beyond it are
            // charged at extra_km_rate once the odometer is read at return.
            $table->boolean('included_km_per_day')->default(true)->after('included_km');
            $table->decimal('extra_km_rate', 10, 2)->nullable()->after('included_km_per_day');
        });

        // Existing packages move into the matching default category (Tuk
        // Tuk Rental / Stays); Surfing and Kitesurfing start out empty.
        DefaultProductCategories::install();
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_category_id');
            $table->dropColumn(['min_hours', 'max_hours', 'included_km_per_day', 'extra_km_rate']);
        });
    }
};
