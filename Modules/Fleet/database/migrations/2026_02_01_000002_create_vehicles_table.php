<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('vehicle_categories')->restrictOnDelete();
            $table->foreignId('base_location_id')->nullable()->constrained('business_locations')->nullOnDelete();
            $table->json('name');
            $table->string('plate_no', 20)->unique();
            $table->string('model')->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('colour')->nullable();
            $table->unsignedTinyInteger('seats')->default(3);
            $table->enum('transmission', ['manual', 'automatic'])->default('manual');
            $table->enum('fuel_type', ['petrol', 'diesel', 'electric'])->default('petrol');
            $table->json('features')->nullable();
            $table->json('description')->nullable();
            $table->enum('status', ['active', 'maintenance', 'retired'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Admin fleet list and the automatic-vehicle-assignment query
            // (Booking, Phase 4) both filter on active-in-category first.
            $table->index(['category_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
