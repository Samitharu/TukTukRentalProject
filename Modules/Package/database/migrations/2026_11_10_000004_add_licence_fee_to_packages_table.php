<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // The Sri Lankan driving permit fee: added once to each booking of a
        // package that needs the customer's licence. Blank = no fee.
        Schema::table('packages', function (Blueprint $table): void {
            $table->decimal('licence_fee', 10, 2)->nullable()->after('requires_licence_documents');
            $table->decimal('licence_fee_eur', 10, 2)->nullable()->after('licence_fee');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['licence_fee', 'licence_fee_eur']);
        });
    }
};
