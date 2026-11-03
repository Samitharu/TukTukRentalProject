<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Tuk tuk bookings have no vehicle: the business sources one from its
     * partners, so vehicle_id stays null and no slots are reserved (see
     * ProductCategory::$unlimited_units).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversible while tuk tuk bookings exist: they have no vehicle
        // to point at.
    }
};
