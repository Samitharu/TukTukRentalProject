<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Airport pickup destinations (Negombo, Colombo, Kandy…): each has
        // its own price, and how long the vehicle is busy for one trip —
        // there and back — which is the time slot a booking reserves.
        Schema::create('transfer_areas', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_areas');
    }
};
