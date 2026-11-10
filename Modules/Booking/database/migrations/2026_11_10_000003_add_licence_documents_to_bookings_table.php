<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Whether the customer must send their driving licence for this
        // booking (copied from the package when booked), and when staff
        // received it. Existing bookings need nothing.
        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('licence_documents_required')->default(false)->after('has_international_permit');
            $table->timestamp('licence_documents_received_at')->nullable()->after('licence_documents_required');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['licence_documents_required', 'licence_documents_received_at']);
        });
    }
};
