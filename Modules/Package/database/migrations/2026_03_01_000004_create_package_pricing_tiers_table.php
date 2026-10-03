<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('package_pricing_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->unsignedSmallInteger('min_days');
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->decimal('price', 10, 2);
            $table->timestamps();

            // A package can't define two tiers starting at the same day
            // count; the admin "no gaps/overlaps" validator (PackagePricingTierValidator)
            // additionally checks continuity, which a DB constraint alone can't express.
            $table->unique(['package_id', 'min_days']);
            $table->index(['package_id', 'min_days', 'max_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_pricing_tiers');
    }
};
