<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Vehicles and stays are no longer registered in the system at all, so
     * every line — existing or added later — takes every booking and
     * reserves nothing. Unit records on file are kept, unused.
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->boolean('unlimited_units')->default(true)->change();
        });

        DB::table('product_categories')->update(['unlimited_units' => true]);
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->boolean('unlimited_units')->default(false)->change();
        });
    }
};
