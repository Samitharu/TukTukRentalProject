<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Google Maps embed address for the map in the public footer (no map
        // while blank).
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('footer_map_url', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('footer_map_url');
        });
    }
};
