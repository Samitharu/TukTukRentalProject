<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('package_vehicles', function (Blueprint $table): void {
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->primary(['package_id', 'vehicle_id']);
        });

        Schema::create('package_categories', function (Blueprint $table): void {
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('vehicle_categories')->cascadeOnDelete();
            $table->primary(['package_id', 'category_id']);
        });

        Schema::create('package_addons', function (Blueprint $table): void {
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $table->boolean('is_included')->default(false);
            $table->primary(['package_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_addons');
        Schema::dropIfExists('package_categories');
        Schema::dropIfExists('package_vehicles');
    }
};
