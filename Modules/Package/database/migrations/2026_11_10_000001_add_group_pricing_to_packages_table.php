<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Priced by group size (e.g. per boat: 3–5 people $10, 6–7 people $18).
        // Its price rows then count people instead of days or hours. Off for
        // every existing package, so nothing about them changes.
        Schema::table('packages', function (Blueprint $table): void {
            $table->boolean('group_pricing')->default(false)->after('pricing_model');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('group_pricing');
        });
    }
};
