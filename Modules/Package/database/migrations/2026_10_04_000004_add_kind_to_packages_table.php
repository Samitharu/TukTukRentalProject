<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            // Same values as vehicle_categories.kind: a package books either
            // a tuk tuk (by the day) or a stay unit (by the night), never a
            // mix — the unrestricted "any unit" fallback is scoped to it.
            $table->enum('kind', ['vehicle', 'stay'])->default('vehicle')->after('id');
            $table->index(['kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropIndex(['kind', 'is_active']);
            $table->dropColumn('kind');
        });
    }
};
