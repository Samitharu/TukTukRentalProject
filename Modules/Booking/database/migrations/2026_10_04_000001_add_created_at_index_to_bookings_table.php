<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin bookings list defaults to "newest first" with no status
 * filter; the existing (status, created_at) index can't serve that order,
 * so MySQL sorted the whole table for every page view (≈150 ms at 120k
 * bookings, growing linearly). A plain created_at index makes it a short
 * index scan.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
    }
};
