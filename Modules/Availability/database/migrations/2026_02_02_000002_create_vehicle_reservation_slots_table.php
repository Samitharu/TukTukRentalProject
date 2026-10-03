<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_reservation_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('slot_date');
            // Polymorphic to Booking\Models\BookingHold or Booking\Models\Booking
            // (Phase 4) — no FK constraint possible/needed on a morph pair.
            $table->string('holdable_type');
            $table->unsignedBigInteger('holdable_id');
            $table->timestamp('created_at')->nullable();

            // THE double-booking guarantee — see docs/03-database-schema.md.
            // Not just a performance index: a duplicate insert attempt for an
            // already-reserved (vehicle, date) fails at the database level.
            $table->unique(['vehicle_id', 'slot_date']);
            $table->index(['holdable_type', 'holdable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_reservation_slots');
    }
};
