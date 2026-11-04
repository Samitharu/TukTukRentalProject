<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff set two LKR rates, one for 1 USD and one for 1 EUR. Every existing
 * rate was against USD.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table): void {
            $table->string('from_code', 3)->default('USD')->after('currency_id');
        });
    }

    public function down(): void
    {
        // Without the column, a EUR rate would be read as a USD one.
        DB::table('exchange_rates')->where('from_code', '!=', 'USD')->delete();

        Schema::table('exchange_rates', function (Blueprint $table): void {
            $table->dropColumn('from_code');
        });
    }
};
