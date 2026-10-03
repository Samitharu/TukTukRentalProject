<?php

declare(strict_types=1);

namespace Modules\Booking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\BookingReference;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;

/**
 * @extends Factory<Booking>
 */
final class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = now()->addDays(3);
        $end = $start->clone()->addDays(2);

        return [
            'reference' => BookingReference::generate(),
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'status' => Booking::STATUS_CONFIRMED,
            'price_breakdown' => [],
            'total_amount' => 50,
            'currency_code' => 'USD',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ];
    }
}
