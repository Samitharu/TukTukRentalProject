<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Add this other package and save on this one": customers booking
 * package_id are offered offered_package_id, and package_id gets cheaper.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('package_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('offered_package_id')->constrained('packages')->cascadeOnDelete();
            $table->decimal('discount', 10, 2);
            $table->decimal('discount_eur', 10, 2);
            $table->timestamps();

            $table->unique(['package_id', 'offered_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_offers');
    }
};
