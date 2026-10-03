<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Collected at the public booking flow's driver-details step
            // (brief §4) but, until now, nowhere to persist to — see
            // docs/0x-phase-5-summary.md.
            $table->boolean('has_international_permit')->default(false)->after('pickup_type');
            $table->text('special_requests')->nullable()->after('has_international_permit');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['has_international_permit', 'special_requests']);
        });
    }
};
