<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Models\Booking;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Pricing\Services\PricingService;
use Spatie\Permission\Models\Permission;

/**
 * Hourly tuk tuk packages: priced per hour (tiers count hours), booked as
 * a single day with real start/return times, and — by design — blocking
 * the tuk tuk for that whole day on the day-granular reservation slots.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    $this->location = BusinessLocation::factory()->create(['is_active' => true]);
    $this->vehicle = Vehicle::factory()->create();

    $this->hourly = Package::factory()->create([
        'name' => ['en' => 'City Hop'],
        'pricing_model' => Package::MODEL_PER_HOUR,
        'min_hours' => 2,
        'max_hours' => 6,
        'min_days' => 3, // ignored: an hourly package is always a single day
    ]);
    // Tiers count hours here: 1-3 h at 10/h, 4+ h at 8/h.
    $this->hourly->pricingTiers()->create(['min_days' => 1, 'max_days' => 3, 'price' => 10]);
    $this->hourly->pricingTiers()->create(['min_days' => 4, 'max_days' => null, 'price' => 8]);
    $this->hourly->vehicles()->attach($this->vehicle->id);
});

function hourlyDriverDetails(): array
{
    return [
        'first_name' => 'Hana',
        'last_name' => 'Hourly',
        'email' => 'hana@example.test',
        'phone' => '+49 151 1234567',
        'nationality' => 'DE',
        'passport_number' => 'C01X00T47',
        'has_valid_licence' => '1',
        'has_international_permit' => '1',
        'marketing_opt_in' => '0',
    ];
}

function startSingleDayRental(string $date = '2027-01-10'): void
{
    test()->post('/en/booking/start', [
        'start_date' => $date,
        'end_date' => $date,
        'pickup_type' => 'office',
        'business_location_id' => test()->location->id,
    ])->assertRedirect('/en/booking/package');
}

it('keeps an hourly package to a single day whatever the form says', function (): void {
    expect($this->hourly->min_days)->toBe(1)
        ->and($this->hourly->max_days)->toBe(1);
});

it('prices an hourly package by the hours, using the tier for that many hours', function (): void {
    $pricing = app(PricingService::class);
    $start = CarbonImmutable::parse('2027-01-10');

    $short = $pricing->calculate($this->hourly, $start, 1, hours: 2);
    $long = $pricing->calculate($this->hourly, $start, 1, hours: 5);

    expect($short->baseAmount)->toEqualWithDelta(20.0, 0.01)  // 2 h × 10
        ->and($long->baseAmount)->toEqualWithDelta(40.0, 0.01) // 5 h × 8
        ->and($long->hours)->toBe(5)
        ->and($long->toArray()['hours'])->toBe(5);
});

it('books an hourly rental end to end with real times and blocks the tuk tuk for that day', function (): void {
    startSingleDayRental();

    $this->get('/en/booking/package')->assertOk()->assertSee('City Hop')->assertSee('09:00');

    $this->post('/en/booking/package', [
        'package_id' => $this->hourly->id,
        'hourly' => [$this->hourly->id => ['start_time' => '09:00', 'hours' => '4']],
    ])->assertRedirect('/en/booking/addons');

    $this->post('/en/booking/addons', [])->assertRedirect('/en/booking/details');
    $this->post('/en/booking/details', hourlyDriverDetails())->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')->assertOk()->assertSee('09:00&ndash;13:00', false);

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    $booking = Booking::query()->firstOrFail();

    expect($booking->start_at->format('Y-m-d H:i'))->toBe('2027-01-10 09:00')
        ->and($booking->end_at->format('Y-m-d H:i'))->toBe('2027-01-10 13:00')
        ->and($booking->hours())->toBe(4)
        ->and((float) $booking->total_amount)->toEqualWithDelta(32.0, 0.01) // 4 h × 8
        ->and(VehicleReservationSlot::query()->where('vehicle_id', $this->vehicle->id)->pluck('slot_date')->map(fn ($d) => substr((string) $d, 0, 10))->all())->toBe(['2027-01-10']);

    // The rest of that day is not bookable — the tuk tuk is out for the day.
    expect(app(AvailabilityService::class)->isRangeFree($this->vehicle->id, CarbonImmutable::parse('2027-01-10'), CarbonImmutable::parse('2027-01-10')))->toBeFalse();
});

it('rejects a length outside the package limits', function (): void {
    startSingleDayRental();

    $this->post('/en/booking/package', [
        'package_id' => $this->hourly->id,
        'hourly' => [$this->hourly->id => ['start_time' => '09:00', 'hours' => '8']],
    ])->assertSessionHasErrors('package_id');
});

it('rejects an hourly rental that would come back after closing', function (): void {
    startSingleDayRental();

    $this->post('/en/booking/package', [
        'package_id' => $this->hourly->id,
        'hourly' => [$this->hourly->id => ['start_time' => '19:00', 'hours' => '5']], // back at 24:00, last return 22:00
    ])->assertSessionHasErrors('package_id');
});

it('does not offer an hourly package for a multi-day rental', function (): void {
    $daily = Package::factory()->create(['name' => ['en' => 'Island Explorer'], 'min_days' => 1]);
    $daily->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);

    $this->post('/en/booking/start', [
        'start_date' => '2027-01-10',
        'end_date' => '2027-01-12',
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ]);

    $this->get('/en/booking/package')->assertOk()->assertSee('Island Explorer')->assertDontSee('City Hop');
});

it('creates and reschedules an hourly booking from the admin, keeping its length', function (): void {
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo('bookings.manage');

    $this->actingAs($admin)->post('/control-panel/bookings', [
        'package_id' => $this->hourly->id,
        'vehicle_id' => (string) $this->vehicle->id,
        'start_date' => '2027-02-01',
        'end_date' => '2027-02-01',
        'start_time' => '10:00',
        'hours' => '3',
        'pickup_type' => 'office',
        'full_name' => 'Walk In',
        'email' => 'walkin@example.test',
    ])->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect($booking->start_at->format('H:i'))->toBe('10:00')
        ->and($booking->end_at->format('H:i'))->toBe('13:00')
        ->and((float) $booking->total_amount)->toEqualWithDelta(30.0, 0.01); // 3 h × 10

    $this->actingAs($admin)->put("/control-panel/bookings/{$booking->id}/dates", [
        'start_date' => '2027-02-02',
        'start_time' => '14:00',
    ])->assertSessionHasNoErrors();

    $booking->refresh();

    expect($booking->start_at->format('Y-m-d H:i'))->toBe('2027-02-02 14:00')
        ->and($booking->end_at->format('Y-m-d H:i'))->toBe('2027-02-02 17:00')
        ->and($booking->reservationSlots()->pluck('slot_date')->map(fn ($d) => substr((string) $d, 0, 10))->all())->toBe(['2027-02-02']);
});

it('requires a start time for an admin hourly booking', function (): void {
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo('bookings.manage');

    $this->actingAs($admin)->post('/control-panel/bookings', [
        'package_id' => $this->hourly->id,
        'start_date' => '2027-02-01',
        'end_date' => '2027-02-01',
        'pickup_type' => 'office',
        'full_name' => 'Walk In',
        'email' => 'walkin@example.test',
    ])->assertSessionHasErrors('start_time');

    expect(Booking::query()->count())->toBe(0);
});
