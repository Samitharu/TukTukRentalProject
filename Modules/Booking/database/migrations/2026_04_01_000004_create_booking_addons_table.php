<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->restrictOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_addons');
    }
};
