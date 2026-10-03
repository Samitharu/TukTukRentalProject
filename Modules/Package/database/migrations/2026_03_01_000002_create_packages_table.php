<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->enum('pricing_model', ['per_day', 'per_week', 'per_month', 'fixed_bundle', 'tiered'])->default('per_day');
            $table->unsignedSmallInteger('min_days')->default(1);
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->unsignedInteger('included_km')->nullable();
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->boolean('deposit_is_percent')->default(false);
            $table->json('cancellation_policy')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Home-page "featured" list and the public package-list query,
            // in the exact order they filter/sort.
            $table->index(['is_active', 'is_featured', 'sort_order']);
            // Validity-window filtering, run on every package list render.
            $table->index(['valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
