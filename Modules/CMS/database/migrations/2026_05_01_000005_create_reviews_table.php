<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('customer_name');
            $table->string('country', 2)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            // Public reviews display and AggregateRating schema generation
            // both filter approved-only, newest first.
            $table->index(['is_approved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
