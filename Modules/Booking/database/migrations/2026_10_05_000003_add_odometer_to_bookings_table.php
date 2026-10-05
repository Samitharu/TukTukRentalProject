<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Snapshot of the package's km allowance at booking time (total
            // for this booking, already multiplied out for per-day
            // allowances), so a later package edit can't change what this
            // customer is charged. Null = unlimited km.
            $table->unsignedInteger('included_km')->nullable()->after('amount_paid');
            $table->decimal('extra_km_rate', 10, 2)->nullable()->after('included_km');
            $table->unsignedInteger('odometer_start')->nullable()->after('extra_km_rate');
            $table->unsignedInteger('odometer_end')->nullable()->after('odometer_start');
        });

        Schema::table('booking_extra_charges', function (Blueprint $table): void {
            $table->enum('type', ['damage', 'fuel', 'late_return', 'extra_km', 'other'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['included_km', 'extra_km_rate', 'odometer_start', 'odometer_end']);
        });
    }
};
