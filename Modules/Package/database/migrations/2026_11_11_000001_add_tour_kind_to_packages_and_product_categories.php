<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // 'tour': a tour plan, booked online for a run of days and charged
        // per day. Reserves no unit (see Package::KIND_TOUR).
        Schema::table('packages', function (Blueprint $table): void {
            $table->enum('kind', ['vehicle', 'stay', 'activity', 'transfer', 'tour'])->default('vehicle')->change();
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->enum('kind', ['vehicle', 'stay', 'activity', 'transfer', 'tour'])->default('vehicle')->change();
        });
    }

    public function down(): void
    {
        // Left in place: rolling back must not break existing tour packages.
    }
};
