<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('availability_blackouts', function (Blueprint $table): void {
            $table->id();
            // Null vehicle_id = business-wide blackout (e.g. public holiday closure).
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Same overlap-range-scan pattern as vehicle_maintenance_logs;
            // both feed AvailabilityService::isRangeFree().
            $table->index(['vehicle_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blackouts');
    }
};
