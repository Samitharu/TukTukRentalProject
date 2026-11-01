<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // The business's Tripadvisor page, linked from a floating button on
        // every public page (hidden while blank).
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('tripadvisor_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('tripadvisor_url');
        });
    }
};
