<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('package_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('price_modifier_type', ['fixed', 'percent']);
            $table->decimal('price_modifier_value', 10, 2);
            // Bitmask, bit 0 = Monday ... bit 6 = Sunday. 127 = every day.
            $table->unsignedTinyInteger('weekday_mask')->default(127);
            $table->smallInteger('priority')->default(0);
            $table->timestamps();

            // The pricing calculator's "which seasons overlap this booking's
            // date range" query — run on every price calculation, including
            // the live JSON recalculation endpoint, so this is a hot-path index.
            $table->index(['package_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_seasons');
    }
};
