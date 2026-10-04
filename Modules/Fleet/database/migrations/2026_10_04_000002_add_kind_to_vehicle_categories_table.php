<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table): void {
            // What a category's units are: tuk tuks rented by the day, or
            // stays (cabanas, rooms) booked by the night. Every unit takes
            // its kind from its category — see VehicleCategory::KINDS.
            $table->enum('kind', ['vehicle', 'stay'])->default('vehicle')->after('id');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
