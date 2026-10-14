<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Where a public booking came from, so staff can spot one person
            // (e.g. a competitor) blocking the fleet with fake bookings — see
            // SuspiciousBookingDetector. Null for admin manual bookings: the
            // IP there is the staff member's, not the customer's.
            $table->string('ip_address', 45)->nullable()->after('locale_at_booking');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            // Comparable snapshots of what was typed at checkout (see
            // BookingFingerprint): the phone's last 9 digits, and a keyed
            // hash of the passport number (the customer's own copy is
            // encrypted with a random IV, so it can't be matched in SQL).
            $table->string('phone_key', 20)->nullable()->after('user_agent');
            $table->string('passport_key', 64)->nullable()->after('phone_key');

            $table->index('ip_address');
            $table->index('phone_key');
            $table->index('passport_key');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['phone_key']);
            $table->dropIndex(['passport_key']);
            $table->dropColumn(['ip_address', 'user_agent', 'phone_key', 'passport_key']);
        });
    }
};
