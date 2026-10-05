<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Availability\Models\BusinessLocation;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Pricing\DataObjects\PriceBreakdown;

/**
 * Every way a second rental can sit against an existing one on the same
 * tuk tuk. Rentals are whole calendar days with the return day included
 * (the tuk tuk comes back that day), so "touching" ranges conflict and
 * only a range starting the day after the return is free.
 */
beforeEach(function (): void {
    config(['availability.buffer_days' => 0]);

    $this->vehicle = Vehicle::factory()->create();
    $this->package = Package::factory()->create();
    $this->package->vehicles()->attach($this->vehicle->id);

    // Customer A: picks up 10 May, returns 12 May.
    $this->bookRange = fn (string $start, string $end, string $email) => app(BookingService::class)->createManualBooking(
        holdData: ['hold_key' => (string) Str::uuid(), 'start_at' => $start, 'end_at' => $end, 'vehicle_id' => $this->vehicle->id, 'package_id' => $this->package->id],
        customerData: ['email' => $email, 'full_name' => 'Matrix Customer', 'pickup_type' => 'office'],
        price: new PriceBreakdown('LKR', 1, 0, 0, [], 0, 0, null, 0, 0, 0),
    );

    ($this->bookRange)('2027-05-10', '2027-05-12', 'a@example.test');
});

it('decides a second rental on the same tuk tuk correctly', function (string $start, string $end, bool $allowed): void {
    $attempt = fn () => ($this->bookRange)($start, $end, 'b@example.test');

    if ($allowed) {
        expect($attempt())->toBeInstanceOf(Booking::class);
    } else {
        expect($attempt)->toThrow(NoVehicleAvailableException::class);
        expect(Booking::query()->count())->toBe(1);
    }
})->with([
    'identical dates' => ['2027-05-10', '2027-05-12', false],
    'ends on A\'s pickup day' => ['2027-05-08', '2027-05-10', false],
    'starts on A\'s return day' => ['2027-05-12', '2027-05-14', false],
    'single day inside A' => ['2027-05-11', '2027-05-11', false],
    'wraps around A' => ['2027-05-09', '2027-05-13', false],
    'overlaps A\'s start' => ['2027-05-09', '2027-05-11', false],
    'overlaps A\'s end' => ['2027-05-11', '2027-05-13', false],
    'the day after A\'s return' => ['2027-05-13', '2027-05-15', true],
    'ends the day before A\'s pickup' => ['2027-05-07', '2027-05-09', true],
    'a different month' => ['2027-06-10', '2027-06-12', true],
]);

it('keeps a buffer day free between rentals when one is configured', function (): void {
    config(['availability.buffer_days' => 1]);

    expect(fn () => ($this->bookRange)('2027-05-13', '2027-05-14', 'b@example.test'))->toThrow(NoVehicleAvailableException::class);
    expect(($this->bookRange)('2027-05-14', '2027-05-15', 'c@example.test'))->toBeInstanceOf(Booking::class);
});

it('frees the dates again when the first booking is cancelled', function (): void {
    app(BookingService::class)->cancel(Booking::query()->firstOrFail(), 'Customer cancelled');

    expect(($this->bookRange)('2027-05-10', '2027-05-12', 'b@example.test'))->toBeInstanceOf(Booking::class);
});

it('rejects an hourly rental on a day the tuk tuk is already out on a daily rental', function (): void {
    $hourly = Package::factory()->create(['pricing_model' => Package::MODEL_PER_HOUR, 'min_hours' => 1, 'max_hours' => 8]);
    $hourly->vehicles()->attach($this->vehicle->id);

    expect(fn () => app(BookingService::class)->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => '2027-05-12 15:00', // A's return day — the tuk tuk is out until it's back
        'end_at' => '2027-05-12 17:00',
        'vehicle_id' => $this->vehicle->id,
        'package_id' => $hourly->id,
    ]))->toThrow(NoVehicleAvailableException::class);
});

it('shows the tuk tuk as fully booked on the website to a second hourly customer for that day', function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $location = BusinessLocation::factory()->create(['is_active' => true]);

    $hourly = Package::factory()->create(['name' => ['en' => 'City Hop'], 'pricing_model' => Package::MODEL_PER_HOUR, 'min_hours' => 1, 'max_hours' => 8]);
    $hourly->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 10]);
    $hourly->vehicles()->attach($this->vehicle->id);

    // Customer A already has the only tuk tuk 09:00–11:00 on 20 May.
    app(BookingService::class)->createManualBooking(
        holdData: ['hold_key' => (string) Str::uuid(), 'start_at' => '2027-05-20 09:00', 'end_at' => '2027-05-20 11:00', 'vehicle_id' => $this->vehicle->id, 'package_id' => $hourly->id],
        customerData: ['email' => 'hourly-a@example.test', 'full_name' => 'Hourly A', 'pickup_type' => 'office'],
        price: new PriceBreakdown('LKR', 1, 20, 0, [], 0, 0, null, 0, 20, 0, hours: 2),
    );

    // Customer B wants 14:00–16:00 the same day on the website.
    $this->post('/en/booking/start', ['start_date' => '2027-05-20', 'end_date' => '2027-05-20', 'pickup_type' => 'office', 'business_location_id' => $location->id]);

    $this->get('/en/booking/package')->assertOk()->assertSee('City Hop')->assertSee('Fully booked for your dates');

    $this->post('/en/booking/package', [
        'package_id' => $hourly->id,
        'hourly' => [$hourly->id => ['start_time' => '14:00', 'hours' => '2']],
    ])->assertSessionHasErrors('package_id');

    expect(Booking::query()->where('vehicle_id', $this->vehicle->id)->whereDate('start_at', '2027-05-20')->count())->toBe(1);
});
