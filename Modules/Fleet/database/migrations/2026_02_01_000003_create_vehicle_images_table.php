<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['vehicle_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_images');
    }
};
