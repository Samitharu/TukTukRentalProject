<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            // How everything in the category is booked — see
            // Modules\Package\Models\Package::KINDS. Every package takes its
            // own `kind` from here, so the booking flow needs no new cases.
            $table->enum('kind', ['vehicle', 'stay', 'activity'])->default('vehicle');
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Public category tabs / listing, in the order they filter/sort.
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
