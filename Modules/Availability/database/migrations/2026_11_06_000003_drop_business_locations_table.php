<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * There is one office, and its address is the business address
 * (BUSINESS_ADDRESS), so there are no locations to manage.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_location_id');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('base_location_id');
        });

        Schema::dropIfExists('business_locations');
    }

    /** Restores the empty table and columns only; dropped locations don't come back. */
    public function down(): void
    {
        Schema::create('business_locations', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->text('address')->nullable();
            $table->string('google_maps_url', 500)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_pickup_point')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('base_location_id')->nullable()->constrained('business_locations')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('business_location_id')->nullable()->constrained('business_locations')->nullOnDelete();
        });
    }
};
