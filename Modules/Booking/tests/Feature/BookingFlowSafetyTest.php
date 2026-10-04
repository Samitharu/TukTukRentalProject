<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Availability\Models\AvailabilityBlackout;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Services\AvailabilityService;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleMaintenanceLog;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;

/**
 * The public wizard's guard rails: what a customer is told (error /
 * warning, in their language) when dates, packages or vehicles stop being
 * bookable, and that a crafted or stale session can never book something
 * the steps themselves would have refused.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    Locale::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => false, 'is_active' => true, 'sort_order' => 2]);

    $this->location = BusinessLocation::factory()->create(['is_active' => true]);
    $this->vehicleA = Vehicle::factory()->create();
    $this->vehicleB = Vehicle::factory()->create();

    $this->package = Package::factory()->create(['min_days' => 1, 'max_days' => null]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
    $this->package->vehicles()->attach([$this->vehicleA->id, $this->vehicleB->id]);

    $this->start = CarbonImmutable::today()->addDays(20);
    $this->end = $this->start->addDays(2);
});

function safetyDriver(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ana', 'last_name' => 'Silva', 'email' => 'ana.silva@example.test',
        'phone' => '+351 912 345 678', 'nationality' => 'PT', 'passport_number' => 'X1234567',
        'has_valid_licence' => '1', 'has_international_permit' => '0', 'marketing_opt_in' => '0',
    ], $overrides);
}

function safetyDates(object $test, string $locale = 'en'): \Illuminate\Testing\TestResponse
{
    return $test->post("/{$locale}/booking/start", [
        'start_date' => $test->start->toDateString(),
        'end_date' => $test->end->toDateString(),
        'pickup_type' => 'office',
        'business_location_id' => $test->location->id,
    ]);
}

function safetyThroughDetails(object $test): void
{
    safetyDates($test)->assertRedirect('/en/booking/package');
    $test->post('/en/booking/package', ['package_id' => $test->package->id])->assertRedirect('/en/booking/addons');
    $test->post('/en/booking/addons', [])->assertRedirect('/en/booking/details');
    $test->post('/en/booking/details', safetyDriver())->assertRedirect('/en/booking/review');
}

function occupy(Vehicle $vehicle, CarbonImmutable $start, CarbonImmutable $end): void
{
    app(BookingService::class)->createHold([
        'hold_key' => (string) Str::uuid(),
        'start_at' => $start->toDateString(),
        'end_at' => $end->toDateString(),
        'vehicle_id' => $vehicle->id,
    ]);
}

// ---- Date step -----------------------------------------------------------

it('rejects a pickup date further ahead than the advance-booking window, with a clear message', function (): void {
    config(['availability.maximum_advance_days' => 30]);

    $this->post('/en/booking/start', [
        'start_date' => now()->addDays(31)->toDateString(),
        'end_date' => now()->addDays(32)->toDateString(),
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ])->assertSessionHasErrors(['start_date' => 'Bookings can be made up to 30 days in advance.']);
});

it('rejects a rental longer than the maximum length (also caps per-day slot rows written)', function (): void {
    config(['booking.max_rental_days' => 30]);

    $this->post('/en/booking/start', [
        'start_date' => $this->start->toDateString(),
        'end_date' => $this->start->addDays(30)->toDateString(), // 31 days inclusive
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ])->assertSessionHasErrors(['end_date' => 'The maximum rental length is 30 days. For longer rentals, please contact us.']);
});

it('shows date errors in the visitor\'s language', function (): void {
    $this->post('/de/booking/start', [
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->toDateString(),
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ])->assertSessionHasErrors(['start_date' => 'Das Abholdatum darf nicht in der Vergangenheit liegen.']);
});

// ---- Package step: availability shown up front ----------------------------

it('labels a package available, "only N left", or fully booked for the chosen dates', function (): void {
    safetyDates($this);
    $this->get('/en/booking/package')->assertOk()->assertSee('Only 2 tuk tuks left for your dates');

    occupy($this->vehicleA, $this->start, $this->end);
    $this->get('/en/booking/package')->assertSee('Only 1 tuk tuk left for your dates');

    occupy($this->vehicleB, $this->start->addDay(), $this->start->addDay());
    $this->get('/en/booking/package')
        ->assertSee('Fully booked for your dates')
        ->assertSee('All our tuk tuks are booked for these dates.')
        ->assertSee('disabled', false)
        ->assertDontSee('Only 1 tuk tuk left');
});

it('counts blackouts and maintenance as unavailable too', function (): void {
    AvailabilityBlackout::query()->create(['vehicle_id' => $this->vehicleA->id, 'starts_on' => $this->end->toDateString(), 'ends_on' => $this->end->addDays(3)->toDateString(), 'reason' => 'Festival']);
    VehicleMaintenanceLog::query()->create([
        'vehicle_id' => $this->vehicleB->id,
        // Starts mid-morning on the return day — the whole day is rented.
        'starts_at' => $this->end->setTime(9, 30), 'ends_at' => $this->end->addDay()->setTime(17, 0),
        'description' => 'Service', 'type' => 'service',
    ]);

    safetyDates($this);

    $this->get('/en/booking/package')->assertSee('Fully booked for your dates');
});

it('refuses to continue with a fully booked package and says why', function (): void {
    occupy($this->vehicleA, $this->start, $this->end);
    occupy($this->vehicleB, $this->start, $this->end);
    safetyDates($this);

    $this->from('/en/booking/package')
        ->post('/en/booking/package', ['package_id' => $this->package->id])
        ->assertRedirect('/en/booking/package')
        ->assertSessionHasErrors(['package_id' => 'Sorry, that package is fully booked for your dates. Please choose another package or change your dates.']);
});

it('warns when the tuk tuk picked on the fleet page is already booked, then auto-assigns another', function (): void {
    occupy($this->vehicleA, $this->start, $this->end);

    $this->get('/en/booking/start?vehicle='.$this->vehicleA->id)->assertOk();
    safetyDates($this);

    $this->get('/en/booking/package')->assertSee('is already booked for these dates', false);

    $this->post('/en/booking/package', ['package_id' => $this->package->id])->assertRedirect('/en/booking/addons');
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', safetyDriver());
    $this->get('/en/booking/review')->assertOk();
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    expect(Booking::query()->sole()->vehicle_id)->toBe($this->vehicleB->id);
});

// ---- Confirm: races, stale and crafted sessions ---------------------------

it('turns "someone else just booked the last tuk tuk" into a friendly message, not an error page', function (): void {
    safetyThroughDetails($this);
    $this->get('/en/booking/review')->assertOk();

    // Between the customer opening the review page and pressing confirm:
    occupy($this->vehicleA, $this->start, $this->end);
    occupy($this->vehicleB, $this->start, $this->end);

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertRedirect('/en/booking/package')
        ->assertSessionHasErrors(['package_id' => 'Sorry, the last available tuk tuk for these dates was just booked by someone else. Please choose another package or change your dates.']);

    expect(Booking::query()->count())->toBe(0);
});

it('creates exactly one booking when "Confirm" is submitted twice', function (): void {
    safetyThroughDetails($this);
    $this->get('/en/booking/review')->assertOk();

    // Simulates the second click's request arriving with the same session
    // state (the first request hasn't cleared it yet when it was read).
    $state = session('booking_flow');
    $first = $this->post('/en/booking/confirm', ['terms_accepted' => '1']);
    $second = $this->withSession(['booking_flow' => $state])->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect(Booking::query()->count())->toBe(1)
        ->and($second->headers->get('Location'))->toBe($first->headers->get('Location'))
        ->and($first->headers->get('Location'))->toContain('/en/booking/confirmation/MTR-');
});

it('rejects a stale session whose pickup date has passed', function (): void {
    safetyThroughDetails($this);
    $this->get('/en/booking/review')->assertOk();

    $this->travelTo($this->start->addDay());

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertRedirect('/en/booking/start')
        ->assertSessionHasErrors(['start_date' => 'Your selected dates can no longer be booked. Please choose new dates.']);

    expect(Booking::query()->count())->toBe(0);
});

it('cannot book an inactive package by putting its id in the start URL', function (): void {
    $hidden = Package::factory()->create(['is_active' => false]);
    $hidden->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 1]);

    $this->get('/en/booking/start?package='.$hidden->id);
    safetyDates($this);
    // Skip the package step entirely and walk straight on.
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', safetyDriver());

    $this->get('/en/booking/review')->assertRedirect('/en/booking/package');
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    expect(Booking::query()->count())->toBe(0);
});

it('cannot book a package for fewer days than its minimum by skipping the package step', function (): void {
    $weekly = Package::factory()->create(['min_days' => 7]);
    $weekly->pricingTiers()->create(['min_days' => 7, 'max_days' => null, 'price' => 15]);

    $this->get('/en/booking/start?package='.$weekly->id);
    safetyDates($this); // 3 days
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', safetyDriver());

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertRedirect('/en/booking/package')
        ->assertSessionHasErrors('package_id');

    expect(Booking::query()->count())->toBe(0);
});

// ---- Eligibility / availability rules -------------------------------------

it('never auto-assigns a retired or in-workshop vehicle attached to a package', function (): void {
    $this->vehicleA->update(['status' => Vehicle::STATUS_RETIRED]);
    $this->vehicleB->update(['status' => Vehicle::STATUS_MAINTENANCE]);

    expect($this->package->fresh()->eligibleVehicleIds())->toBe([]);
});

it('treats maintenance starting during the return day as a conflict', function (): void {
    VehicleMaintenanceLog::query()->create([
        'vehicle_id' => $this->vehicleA->id,
        'starts_at' => $this->end->setTime(14, 0), 'ends_at' => $this->end->setTime(18, 0),
        'description' => 'Brake check', 'type' => 'service',
    ]);

    expect(app(AvailabilityService::class)->isRangeFree($this->vehicleA->id, $this->start, $this->end))->toBeFalse();
});

it('treats a blackout ending on the pickup day as a conflict', function (): void {
    AvailabilityBlackout::query()->create(['vehicle_id' => $this->vehicleA->id, 'starts_on' => $this->start->subDays(3)->toDateString(), 'ends_on' => $this->start->toDateString(), 'reason' => 'Holiday']);

    expect(app(AvailabilityService::class)->isRangeFree($this->vehicleA->id, $this->start, $this->end))->toBeFalse();
});
