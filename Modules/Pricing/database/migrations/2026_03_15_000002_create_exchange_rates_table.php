<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('rate', 12, 6);
            $table->dateTime('fetched_at');

            // "Latest rate for currency X" (ORDER BY fetched_at DESC LIMIT 1)
            // range-scans this instead of scanning the whole rate history.
            $table->index(['currency_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
