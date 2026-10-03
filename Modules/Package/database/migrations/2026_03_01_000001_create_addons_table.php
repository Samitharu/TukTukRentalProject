<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('pricing_unit', ['flat', 'per_day'])->default('flat');
            $table->unsignedTinyInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
