<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('business_locations', function (Blueprint $table): void {
            // Same as vehicles.google_maps_url: the "Share → Copy link" URL,
            // shown to customers as-is (it can carry the business listing).
            $table->string('google_maps_url', 500)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('business_locations', function (Blueprint $table): void {
            $table->dropColumn('google_maps_url');
        });
    }
};
