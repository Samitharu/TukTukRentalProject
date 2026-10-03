<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_maintenance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('type');
            $table->text('description')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The Availability engine's overlap check for a vehicle in a
            // date range range-scans this index.
            $table->index(['vehicle_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_logs');
    }
};
