<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            // Non-sequential, random (MTR-XXXXXXXX) — deliberate IDOR
            // prevention (brief §8): a customer's own reference can't be
            // used to guess another customer's booking.
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('business_location_id')->nullable()->constrained('business_locations')->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->enum('pickup_type', ['office', 'delivery'])->default('office');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', [
                'hold', 'pending_payment', 'confirmed', 'active', 'completed',
                'cancelled', 'no_show', 'expired',
            ])->default('hold');
            $table->json('price_breakdown');
            $table->decimal('total_amount', 10, 2);
            // A currency *code* snapshot (not an FK to currencies.id):
            // price_breakdown is itself a point-in-time snapshot of amounts
            // already converted into this currency, so display never needs
            // a join back to the currencies table.
            $table->string('currency_code', 3);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('locale_at_booking', 5)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            // The conflict-check + admin Gantt calendar query: "this
            // vehicle's non-cancelled bookings overlapping this range."
            $table->index(['vehicle_id', 'status', 'start_at', 'end_at']);
            // Admin bookings list default view (recent, filterable by status)
            // and the hold-expiry/reporting jobs.
            $table->index(['status', 'created_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
