<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Airport pickup bookings: start_at/end_at carry the real trip times
        // (start + the area's duration), and these say where and which flight.
        Schema::table('bookings', function (Blueprint $table): void {
            $table->enum('transfer_direction', ['arrival', 'departure'])->nullable()->after('pickup_type');
            $table->string('flight_number', 20)->nullable()->after('transfer_direction');
            $table->string('transfer_address', 255)->nullable()->after('flight_number');
            $table->foreignId('transfer_area_id')->nullable()->after('transfer_address')
                ->constrained('transfer_areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transfer_area_id');
            $table->dropColumn(['transfer_direction', 'flight_number', 'transfer_address']);
        });
    }
};
