<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Blackout dates are no longer used: tuk tuks can be hired on any day.
     * Taking one vehicle out of service is done with its maintenance log.
     */
    public function up(): void
    {
        Schema::dropIfExists('availability_blackouts');
    }

    public function down(): void
    {
        Schema::create('availability_blackouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vehicle_id', 'starts_on', 'ends_on']);
        });
    }
};
