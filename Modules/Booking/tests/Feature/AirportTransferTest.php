<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Availability\Models\TransferArea;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Booking\Exceptions\NoVehicleAvailableException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Fleet\Models\VehicleMaintenanceLog;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Modules\Pricing\Services\PricingService;
use Spatie\Permission\Models\Permission;

/**
 * Airport pickups reserve a vehicle for a time slot (pickup time + the
 * destination area's trip duration, plus a buffer between trips) — not
 * whole days — and are priced as package price + area price.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    config(['booking.transfer_buffer_minutes' => 30]);

    // The time-slot engine, with a line that still reserves registered units.
    $this->line = ProductCategory::query()->where('kind', Package::KIND_TRANSFER)->firstOrFail();
    $this->line->update(['unlimited_units' => false]);
    $this->vanType = VehicleCategory::factory()->create(['kind' => VehicleCategory::KIND_TRANSFER, 'name' => ['en' => 'Airport vans'], 'product_category_id' => $this->line->id]);
    $this->van = Vehicle::factory()->create(['category_id' => $this->vanType->id, 'name' => ['en' => 'Toyota KDH'], 'seats' => 10]);

    $this->package = Package::factory()->create([
        'name' => ['en' => 'Private van'],
        'product_category_id' => $this->line->id,
        'pricing_model' => Package::MODEL_FIXED_BUNDLE,
    ]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 10]);

    $this->area = TransferArea::factory()->create(['name' => ['en' => 'Negombo'], 'price' => 25, 'duration_minutes' => 90]);
});

function bookTrip($test, string $start, string $end): Booking
{
    $price = app(PricingService::class)->calculate($test->package, CarbonImmutable::parse($start), 1, transferArea: $test->area);

    return app(BookingService::class)->createManualBooking(
        holdData: ['hold_key' => (string) Str::uuid(), 'start_at' => $start, 'end_at' => $end, 'package_id' => $test->package->id],
        customerData: [
            'email' => Str::lower(Str::random(8)).'@example.test', 'full_name' => 'Trip Customer',
            'transfer_direction' => Booking::TRANSFER_ARRIVAL, 'flight_number' => 'UL504',
            'transfer_address' => 'Jetwing Blue, Negombo', 'transfer_area_id' => $test->area->id,
        ],
        price: $price,
        notifyCustomer: false,
    );
}

function transferStep1(array $overrides = []): array
{
    return array_merge([
        'transfer_direction' => 'arrival',
        'start_date' => '2027-03-10',
        'transfer_time' => '14:30',
        'flight_number' => 'ul 504',
        'transfer_area_id' => test()->area->id,
        'transfer_address' => 'Jetwing Blue, Negombo',
    ], $overrides);
}

// ---- Time-slot availability ----------------------------------------------

it('reserves only the trip\'s time slot, so one van does several trips a day', function (): void {
    bookTrip($this, '2027-03-10 06:00:00', '2027-03-10 07:30:00');
    bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00');

    expect(Booking::query()->count())->toBe(2)
        ->and(VehicleReservationSlot::query()->count())->toBe(0); // never whole-day slots
});

it('refuses an overlapping trip on the only van', function (): void {
    bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00');

    expect(fn () => bookTrip($this, '2027-03-10 15:00:00', '2027-03-10 16:30:00'))->toThrow(NoVehicleAvailableException::class);
});

it('keeps the buffer free between two trips', function (): void {
    bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00');

    expect(fn () => bookTrip($this, '2027-03-10 16:20:00', '2027-03-10 17:50:00'))->toThrow(NoVehicleAvailableException::class);

    bookTrip($this, '2027-03-10 16:30:00', '2027-03-10 18:00:00'); // exactly the 30-minute buffer later
    expect(Booking::query()->count())->toBe(2);
});

it('frees the slot when a trip is cancelled', function (): void {
    $first = bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00');
    app(BookingService::class)->cancel($first, 'Flight cancelled');

    bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00');
    expect(Booking::query()->where('status', Booking::STATUS_CONFIRMED)->count())->toBe(1);
});

it('ignores an abandoned checkout once its hold has expired', function (): void {
    $hold = app(BookingService::class)->createHold([
        'hold_key' => (string) Str::uuid(), 'start_at' => '2027-03-10 14:30:00', 'end_at' => '2027-03-10 16:00:00',
        'package_id' => $this->package->id,
    ]);

    expect(fn () => bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00'))->toThrow(NoVehicleAvailableException::class);

    $hold->update(['expires_at' => now()->subMinute()]);
    bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00');
    expect(Booking::query()->count())->toBe(1);
});

it('blocks trips during the van\'s maintenance', function (): void {
    VehicleMaintenanceLog::query()->create([
        'vehicle_id' => $this->van->id, 'type' => 'service', 'description' => 'Service',
        'starts_at' => '2027-03-10 13:00:00', 'ends_at' => '2027-03-10 15:00:00',
    ]);

    expect(fn () => bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00'))->toThrow(NoVehicleAvailableException::class);
    bookTrip($this, '2027-03-10 18:00:00', '2027-03-10 19:30:00');
});

it('never assigns an airport van to a tuk tuk rental', function (): void {
    $tukTuk = Package::factory()->create();

    expect($tukTuk->eligibleVehicleIds())->not->toContain($this->van->id);
});

// ---- Pricing ---------------------------------------------------------------

it('prices a trip as the package price plus the destination area', function (): void {
    $price = app(PricingService::class)->calculate($this->package, CarbonImmutable::parse('2027-03-10'), 1, transferArea: $this->area);

    expect($price->baseAmount)->toBe(10.0)
        ->and($price->transferFee)->toBe(25.0)
        ->and($price->total)->toBe(35.0);
});

// ---- Public booking flow ---------------------------------------------------

it('books an airport pickup online: flight, time, area and address', function (): void {
    $this->get('/en/booking/start?line='.$this->line->id)
        ->assertOk()
        ->assertSee('name="flight_number"', false)
        ->assertSee('name="transfer_area_id"', false)
        ->assertDontSee('name="end_date"', false);

    $this->post('/en/booking/start', transferStep1())->assertSessionHasNoErrors()->assertRedirect('/en/booking/package');
    $this->get('/en/booking/package')->assertOk()->assertSee('Private van');
    $this->post('/en/booking/package', ['package_id' => $this->package->id])->assertRedirect('/en/booking/addons');
    $this->post('/en/booking/addons', []);

    $this->get('/en/booking/details')->assertOk()->assertDontSee('name="has_valid_licence"', false);
    $this->post('/en/booking/details', [
        'first_name' => 'Ana', 'last_name' => 'Silva', 'email' => 'ana@example.test', 'phone' => '+351 912 000 000',
        'nationality' => 'PT', 'passport_number' => 'X1', 'marketing_opt_in' => '0',
    ])->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')->assertOk()->assertSee('UL504')->assertSee('Negombo')->assertSee('14:30');
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    $booking = Booking::query()->sole();
    expect($booking->isTransfer())->toBeTrue()
        ->and($booking->vehicle_id)->toBe($this->van->id)
        ->and($booking->flight_number)->toBe('UL504')
        ->and($booking->transfer_direction)->toBe('arrival')
        ->and($booking->transfer_area_id)->toBe($this->area->id)
        ->and($booking->transfer_address)->toBe('Jetwing Blue, Negombo')
        ->and($booking->start_at->format('Y-m-d H:i'))->toBe('2027-03-10 14:30')
        ->and($booking->end_at->format('Y-m-d H:i'))->toBe('2027-03-10 16:00') // + the area's 90 minutes
        ->and((float) $booking->total_amount)->toBe(35.0);

    $this->get('/en/booking/confirmation/'.$booking->reference)->assertOk()->assertSee('UL504')->assertSee('Jetwing Blue, Negombo');
    $this->get('/en/booking/confirmation/'.$booking->reference.'/receipt')->assertOk();
});

it('shows the van as taken for an overlapping time, but free later that day', function (): void {
    bookTrip($this, '2027-03-10 14:30:00', '2027-03-10 16:00:00');

    $this->get('/en/booking/start?line='.$this->line->id);
    $this->post('/en/booking/start', transferStep1(['transfer_time' => '15:00']));
    $this->get('/en/booking/package')->assertOk()->assertSee('Fully booked for your dates');
    $this->post('/en/booking/package', ['package_id' => $this->package->id])->assertSessionHasErrors('package_id');

    $this->post('/en/booking/start', transferStep1(['transfer_time' => '19:00']));
    $this->post('/en/booking/package', ['package_id' => $this->package->id])->assertRedirect('/en/booking/addons');
});

it('validates the airport pickup details', function (): void {
    $this->get('/en/booking/start?line='.$this->line->id);

    $this->post('/en/booking/start', transferStep1(['flight_number' => 'not a flight', 'transfer_address' => '', 'transfer_time' => '25:00']))
        ->assertSessionHasErrors(['flight_number', 'transfer_address', 'transfer_time']);

    // Optional, but a switched-off area can't be chosen.
    $this->area->update(['is_active' => false]);
    $this->post('/en/booking/start', transferStep1())->assertSessionHasErrors('transfer_area_id');
});

it('needs a few hours\' notice for an online airport pickup', function (): void {
    $this->get('/en/booking/start?line='.$this->line->id);
    $soon = CarbonImmutable::now(config('booking.business_timezone'))->addHour();

    $this->post('/en/booking/start', transferStep1(['start_date' => $soon->toDateString(), 'transfer_time' => $soon->format('H:i')]))
        ->assertSessionHasErrors('transfer_time');
});

// ---- Control panel ---------------------------------------------------------

it('lets staff book an airport pickup and move it to another time', function (): void {
    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo(['bookings.view', 'bookings.manage']);

    $payload = [
        'package_id' => $this->package->id,
        'start_date' => '2027-03-10',
        'transfer_direction' => 'departure',
        'transfer_time' => '05:00',
        'flight_number' => 'EK 651',
        'transfer_area_id' => $this->area->id,
        'transfer_address' => 'Villa 12, Negombo',
        'full_name' => 'Phone Customer',
        'email' => 'phone@example.test',
    ];

    $this->actingAs($admin)->postJson('/control-panel/bookings/quote', $payload)
        ->assertOk()->assertJson(['transfer_fee' => 25.0, 'total' => 35.0, 'units_free' => 1]);

    $this->actingAs($admin)->post('/control-panel/bookings', [...$payload, 'flight_number' => ''])->assertSessionHasErrors('flight_number');
    $this->actingAs($admin)->post('/control-panel/bookings', $payload)->assertSessionHasNoErrors()->assertRedirect();

    $booking = Booking::query()->sole();
    expect($booking->transfer_direction)->toBe('departure')
        ->and($booking->flight_number)->toBe('EK651')
        ->and($booking->start_at->format('H:i'))->toBe('05:00')
        ->and($booking->end_at->format('H:i'))->toBe('06:30');

    $this->actingAs($admin)->get('/control-panel/bookings/'.$booking->id)->assertOk()->assertSee('EK651')->assertSee('Villa 12, Negombo');

    $this->actingAs($admin)->put('/control-panel/bookings/'.$booking->id.'/dates', ['start_date' => '2027-03-11', 'transfer_time' => '09:15'])
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->start_at->format('Y-m-d H:i'))->toBe('2027-03-11 09:15')
        ->and($booking->fresh()->end_at->format('Y-m-d H:i'))->toBe('2027-03-11 10:45');
});

// ---- Destination area is optional ------------------------------------------------

it('books an airport pickup online without a destination area, at the package price', function (): void {
    $this->get('/en/booking/start?line='.$this->line->id)->assertOk()->assertSee('Destination area (optional)')->assertSee('Not listed / not sure');

    $this->post('/en/booking/start', transferStep1(['transfer_area_id' => '']))->assertSessionHasNoErrors()->assertRedirect('/en/booking/package');
    $this->post('/en/booking/package', ['package_id' => $this->package->id])->assertRedirect('/en/booking/addons');
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', [
        'first_name' => 'Ana', 'last_name' => 'Silva', 'email' => 'ana@example.test', 'phone' => '+351 912 000 000',
        'nationality' => 'PT', 'passport_number' => 'X1', 'marketing_opt_in' => '0',
    ])->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')->assertOk()->assertSee('UL504')->assertDontSee('Negombo &middot;', false);
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    $booking = Booking::query()->sole();
    expect($booking->transfer_area_id)->toBeNull()
        ->and($booking->flight_number)->toBe('UL504')
        ->and($booking->transfer_address)->toBe('Jetwing Blue, Negombo')
        ->and((float) $booking->total_amount)->toBe(10.0)
        ->and($booking->end_at->format('Y-m-d H:i'))->toBe('2027-03-10 16:30'); // the 2-hour trip length without an area

    $this->get('/en/booking/confirmation/'.$booking->reference)->assertOk()->assertDontSee('Destination area');
    $this->get('/en/booking/confirmation/'.$booking->reference.'/receipt')->assertOk();
});

it('still needs the area when the package has no price of its own', function (): void {
    $this->package->pricingTiers()->update(['price' => 0]);

    $this->get('/en/booking/start?line='.$this->line->id);
    $this->post('/en/booking/start', transferStep1(['transfer_area_id' => '']))->assertRedirect('/en/booking/package');

    $this->post('/en/booking/package', ['package_id' => $this->package->id])
        ->assertRedirect('/en/booking/start')
        ->assertSessionHasErrors('transfer_area_id');

    expect(Booking::query()->count())->toBe(0);
});

it('lets staff book an airport pickup without an area, unless the package needs one', function (): void {
    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo(['bookings.view', 'bookings.manage']);

    $payload = [
        'package_id' => $this->package->id, 'start_date' => '2027-03-10', 'transfer_direction' => 'arrival',
        'transfer_time' => '05:00', 'flight_number' => 'EK 651', 'transfer_address' => 'Villa 12, Negombo',
        'full_name' => 'Phone Customer', 'email' => 'phone@example.test',
    ];

    $this->actingAs($admin)->post('/control-panel/bookings', $payload)->assertSessionHasNoErrors()->assertRedirect();

    $booking = Booking::query()->sole();
    expect($booking->transfer_area_id)->toBeNull()->and((float) $booking->total_amount)->toBe(10.0);
    $this->actingAs($admin)->get('/control-panel/bookings/'.$booking->id)->assertOk();

    $this->package->pricingTiers()->update(['price' => 0]);
    $this->actingAs($admin)->post('/control-panel/bookings', [...$payload, 'transfer_time' => '18:00'])->assertSessionHasErrors('transfer_area_id');
});

it('lets an airport pickup added to another booking skip the area', function (): void {
    $data = ['start_date' => '2027-03-10', 'end_date' => '2027-03-10', 'transfer_direction' => 'arrival', 'transfer_time' => '14:30', 'flight_number' => 'UL504', 'transfer_address' => 'Jetwing Blue'];
    $plan = app(Modules\Booking\Services\OfferedBookingPlanner::class)->plan($this->package, $data);

    expect(Modules\Booking\Services\OfferedBookingPlanner::problems($this->package, $data))->toBe([])
        ->and($plan['price']->total)->toBe(10.0)
        ->and($plan['end_at'])->toBe('2027-03-10 16:30:00')
        ->and($plan['customer']['flight_number'])->toBe('UL504')
        ->and($plan['customer']['transfer_area_id'])->toBeNull();

    $this->package->pricingTiers()->update(['price' => 0]);
    expect(Modules\Booking\Services\OfferedBookingPlanner::problems($this->package->fresh(), $data))->toHaveKey('offer.transfer_area_id');
});
