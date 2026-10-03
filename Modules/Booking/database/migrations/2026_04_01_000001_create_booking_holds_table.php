<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table): void {
            $table->id();
            // Idempotency key from the client — a repeat POST of the same
            // checkout form returns the existing hold instead of creating a
            // second one (brief §6 point 5).
            $table->string('hold_key', 64)->unique();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('vehicle_categories')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->cascadeOnDelete();
            $table->string('customer_session_id', 64)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('expires_at');
            $table->enum('status', ['active', 'converted', 'expired', 'released'])->default('active');
            $table->timestamps();

            // The every-minute ReleaseExpiredHolds job filters exactly on
            // status='active' AND expires_at < now(); composite index keeps
            // that a tight range scan even with thousands of stale holds.
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
