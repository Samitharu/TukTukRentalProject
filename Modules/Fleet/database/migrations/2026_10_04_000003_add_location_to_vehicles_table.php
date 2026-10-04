<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            // Cabanas and rooms are units too, but have no plate, gearbox or
            // fuel. plate_no stays UNIQUE — multiple NULLs are allowed.
            $table->string('plate_no', 20)->nullable()->change();
            $table->enum('transmission', ['manual', 'automatic'])->nullable()->default(null)->change();
            $table->enum('fuel_type', ['petrol', 'diesel', 'electric'])->nullable()->default(null)->change();

            // Where the unit physically is. Stays are spread over several
            // places, so each one carries its own pin rather than pointing
            // at a shared business location.
            $table->text('address')->nullable()->after('description');
            $table->string('google_maps_url', 500)->nullable()->after('address');
            $table->decimal('lat', 10, 7)->nullable()->after('google_maps_url');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn(['address', 'google_maps_url', 'lat', 'lng']);
        });
    }
};
