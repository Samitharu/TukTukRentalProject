<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A customer can always book a rental, even when every vehicle is
     * already out for those dates: such a booking is taken without a
     * vehicle (vehicle_id null, no reservation slots) and staff assign one
     * later from the booking page.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversible while unassigned bookings exist: they would have
        // no vehicle to point at.
    }
};
