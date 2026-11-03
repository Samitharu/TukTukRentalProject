<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A line whose vehicles aren't registered here because the business can
     * always source one from its partners: every booking is accepted and
     * none reserves a unit. Tuk tuks work this way; SUVs, bikes, airport
     * vehicles and stays are registered and can't be double-booked.
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->boolean('unlimited_units')->default(false)->after('kind');
        });

        $tukTukLine = DB::table('product_categories')->where('name->en', 'Tuk Tuk Rental')->value('id')
            ?? DB::table('product_categories')->where('kind', 'vehicle')->orderBy('sort_order')->orderBy('id')->value('id');

        if ($tukTukLine !== null) {
            DB::table('product_categories')->where('id', $tukTukLine)->update(['unlimited_units' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropColumn('unlimited_units');
        });
    }
};
