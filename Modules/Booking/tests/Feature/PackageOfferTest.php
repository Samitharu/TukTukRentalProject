<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\Availability\Models\TransferArea;
use Modules\Booking\Mail\BookingConfirmationMail;
use Modules\Booking\Mail\NewBookingPlacedMail;
use Modules\Booking\Models\Booking;
use Modules\Customer\Models\Customer;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageOffer;
use Modules\Package\Models\ProductCategory;
use Spatie\Permission\Models\Permission;

/**
 * Airport pickup $15 / €14, or $12 / €11 when a tuk tuk package is booked too:
 * the customer adds it on the review step without entering their details again.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    $line = fn (string $kind) => ProductCategory::query()->where('kind', $kind)->orderBy('sort_order')->firstOrFail();

    $this->airport = Package::factory()->create(['name' => ['en' => 'Airport Car'], 'product_category_id' => $line(Package::KIND_TRANSFER)->id, 'pricing_model' => Package::MODEL_FIXED_BUNDLE, 'min_days' => 1, 'max_days' => null]);
    $this->airport->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 5, 'price_eur' => 4]);
    $this->area = TransferArea::factory()->create(['price' => 10, 'price_eur' => 10, 'is_active' => true]);

    $this->tukTuk = Package::factory()->create(['name' => ['en' => 'Tuk Tuk Daily'], 'product_category_id' => $line(Package::KIND_VEHICLE)->id, 'pricing_model' => Package::MODEL_PER_DAY, 'min_days' => 2, 'max_days' => null]);
    $this->tukTuk->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20, 'price_eur' => 18]);

    $this->offer = PackageOffer::query()->create(['package_id' => $this->airport->id, 'offered_package_id' => $this->tukTuk->id, 'discount' => 3, 'discount_eur' => 3]);
});

function offerAirportToReview(): void
{
    test()->get('/en/booking/start?package='.test()->airport->id)->assertOk();
    test()->post('/en/booking/start', [
        'start_date' => now()->addDays(10)->toDateString(),
        'transfer_direction' => 'arrival',
        'transfer_time' => '14:30',
        'flight_number' => 'UL 504',
        'transfer_area_id' => test()->area->id,
        'transfer_address' => 'Beach Hotel, Negombo',
    ])->assertRedirect('/en/booking/package');
    test()->post('/en/booking/package', ['package_id' => test()->airport->id])->assertRedirect('/en/booking/addons');
    test()->post('/en/booking/addons', [])->assertRedirect('/en/booking/details');
    test()->post('/en/booking/details', [
        'first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456',
        'nationality' => 'GB', 'passport_number' => 'P1234567', 'marketing_opt_in' => '0',
    ])->assertRedirect('/en/booking/review');
}

function tukTukOfferFields(array $overrides = []): array
{
    return ['offer' => [
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(12)->toDateString(),
        'has_valid_licence' => '1',
        'has_international_permit' => '1',
        ...$overrides,
    ]];
}

it('offers the other package on the review step, and only on the package it is set on', function (): void {
    offerAirportToReview();

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('Save $3.00 / €3.00 on Airport Car')
        ->assertSee('Add Tuk Tuk Daily and save $3.00 / €3.00')
        ->assertSeeInOrder(['$15.00 / €14.00', '$12.00 / €11.00']); // this customer's price before and after

    $this->tukTuk->update(['is_active' => false]);

    $this->get('/en/booking/review')->assertOk()->assertDontSee('Add Tuk Tuk Daily');
});

it('flags the saving wherever the package is shown, before the review step', function (): void {
    $flag = 'Save $3.00 / €3.00 when you add Tuk Tuk Daily';

    $this->get('/en/packages')->assertOk()->assertSee($flag);
    $this->get('/en/packages/'.$this->airport->slugFor('en'))->assertOk()->assertSee($flag);

    $this->airport->update(['is_featured' => true]);
    $this->get('/en')->assertOk()->assertSee($flag);

    $this->get('/en/packages/'.$this->tukTuk->slugFor('en'))->assertOk()->assertDontSee('when you add');

    $this->get('/en/booking/start?package='.$this->airport->id)->assertOk();
    $this->post('/en/booking/start', [
        'start_date' => now()->addDays(10)->toDateString(),
        'transfer_direction' => 'arrival',
        'transfer_time' => '14:30',
        'flight_number' => 'UL 504',
        'transfer_area_id' => $this->area->id,
        'transfer_address' => 'Beach Hotel, Negombo',
    ]);
    $this->get('/en/booking/package')->assertOk()->assertSee($flag);

    $this->tukTuk->update(['is_active' => false]);

    $this->get('/en/packages')->assertOk()->assertDontSee('when you add');
});

it('does not flag a saving on a package booked by message', function (): void {
    $this->airport->update(['kind' => Package::KIND_ACTIVITY]);

    $this->get('/en/packages/'.$this->airport->slugFor('en'))->assertOk()->assertDontSee('when you add');
});

it('asks only for what the added package needs, then books both for one customer', function (): void {
    Mail::fake();
    offerAirportToReview();

    $this->get("/en/booking/offer/{$this->offer->id}")
        ->assertOk()
        ->assertSee('Add Tuk Tuk Daily')
        ->assertSee(__('core::front.booking_has_valid_licence'))
        ->assertDontSee('name="offer[email]"', false);

    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields())->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('-$3.00 / -€3.00')
        ->assertSee('$12.00 / €11.00') // the airport trip after the saving
        ->assertSee('$60.00 / €54.00') // tuk tuk, 3 days
        ->assertSee('$72.00 / €65.00') // both
        ->assertDontSee('Add Tuk Tuk Daily and save');

    $confirm = $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect(Booking::query()->count())->toBe(2)
        ->and(Customer::query()->count())->toBe(1);

    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();
    $tukTuk = Booking::query()->where('package_id', $this->tukTuk->id)->sole();

    expect((float) $airport->total_amount)->toBe(12.0)
        ->and($airport->totalEur())->toBe(11.0)
        ->and($airport->flight_number)->toBe('UL504')
        ->and($airport->linked_booking_id)->toBe($tukTuk->id)
        ->and((float) $tukTuk->total_amount)->toBe(60.0)
        ->and($tukTuk->linked_booking_id)->toBe($airport->id)
        ->and($tukTuk->transfer_direction)->toBeNull()
        ->and($tukTuk->has_international_permit)->toBeTrue()
        ->and($tukTuk->customer_id)->toBe($airport->customer_id);

    expect($confirm->headers->get('Location'))->toContain($airport->reference);

    $this->get($confirm->headers->get('Location'))
        ->assertOk()
        ->assertSee($tukTuk->reference)
        ->assertSee('$72.00 / €65.00');

    Mail::assertQueued(BookingConfirmationMail::class, 2);
});

it('shows the saving and the other booking in the emails and on the receipt', function (): void {
    Mail::fake();
    offerAirportToReview();
    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields());
    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();
    $tukTuk = Booking::query()->where('package_id', $this->tukTuk->id)->sole();

    expect((new BookingConfirmationMail($airport))->locale('en')->render())
        ->toContain('-$3.00 / -€3.00')
        ->toContain('$12.00 / €11.00')
        ->toContain($tukTuk->reference)
        ->not->toContain('LKR');

    expect((new NewBookingPlacedMail($airport))->render())
        ->toContain('-$3.00 / -€3.00')
        ->toContain($tukTuk->reference);

    $this->get("/en/booking/confirmation/{$airport->reference}/receipt")->assertOk();
    $this->get("/en/booking/confirmation/{$tukTuk->reference}")->assertOk()->assertSee($airport->reference);
});

it('books only one pair when Confirm is pressed twice', function (): void {
    Mail::fake();
    offerAirportToReview();
    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields());
    $this->get('/en/booking/review');

    $state = session('booking_flow');

    $first = $this->post('/en/booking/confirm', ['terms_accepted' => '1']);
    $this->withSession(['booking_flow' => $state]);
    $second = $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect(Booking::query()->count())->toBe(2)
        ->and($second->headers->get('Location'))->toBe($first->headers->get('Location'));
});

it('holds the added package to its own rules', function (): void {
    offerAirportToReview();

    // This tuk tuk package needs at least 2 days.
    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields(['end_date' => now()->addDays(10)->toDateString()]))
        ->assertSessionHasErrors('offer.end_date');

    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields(['has_valid_licence' => '0']))
        ->assertSessionHasErrors('offer.has_valid_licence');

    expect(session('booking_flow.offer'))->toBeNull();
});

it('can add an airport pickup with its trip details, and remove an offer again', function (): void {
    $this->offer->update(['package_id' => $this->tukTuk->id, 'offered_package_id' => $this->airport->id]);

    $this->get('/en/booking/start?package='.$this->tukTuk->id);
    $this->post('/en/booking/start', ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString()]);
    $this->post('/en/booking/package', ['package_id' => $this->tukTuk->id]);
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', [
        'first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456',
        'nationality' => 'GB', 'passport_number' => 'P1234567', 'has_valid_licence' => '1', 'has_international_permit' => '0', 'marketing_opt_in' => '0',
    ]);

    $this->get("/en/booking/offer/{$this->offer->id}")->assertOk()->assertDontSee(__('core::front.booking_has_valid_licence'));

    $trip = ['offer' => [
        'start_date' => now()->addDays(10)->toDateString(), 'transfer_direction' => 'departure', 'transfer_time' => '09:00',
        'flight_number' => 'ul 504', 'transfer_area_id' => $this->area->id, 'transfer_address' => 'Beach Hotel',
    ]];

    $this->post("/en/booking/offer/{$this->offer->id}", $trip)->assertRedirect('/en/booking/review');
    $this->get('/en/booking/review')->assertOk()->assertSee('-$3.00 / -€3.00')->assertSee('$37.00 / €33.00');

    $this->post('/en/booking/offer-remove')->assertRedirect('/en/booking/review');
    $this->get('/en/booking/review')->assertOk()->assertSee('$40.00 / €36.00')->assertSee('Add Airport Car and save');

    $this->post("/en/booking/offer/{$this->offer->id}", $trip);
    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $added = Booking::query()->where('package_id', $this->airport->id)->sole();

    expect($added->transfer_direction)->toBe('departure')
        ->and($added->flight_number)->toBe('UL504')
        ->and($added->transfer_area_id)->toBe($this->area->id)
        ->and((float) $added->total_amount)->toBe(15.0);
});

it('removes the saving when the offer stops before confirming', function (): void {
    offerAirportToReview();
    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields());

    $this->offer->delete();

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertRedirect('/en/booking/review')
        ->assertSessionHasErrors('booking');

    expect(Booking::query()->count())->toBe(0);
    $this->get('/en/booking/review')->assertOk()->assertSee('$15.00 / €14.00')->assertDontSee('-$3.00');
});

it('never makes a booking negative', function (): void {
    $this->offer->update(['discount' => 100, 'discount_eur' => 100]);
    offerAirportToReview();
    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields());
    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();

    expect((float) $airport->total_amount)->toBe(0.0)
        ->and($airport->totalEur())->toBe(0.0);
});

// ---- Adding any other package -----------------------------------------------

function addablePackage(string $name, string $kind, float $price, float $priceEur): Package
{
    $package = Package::factory()->create(['name' => ['en' => $name], 'product_category_id' => ProductCategory::query()->where('kind', $kind)->orderBy('sort_order')->firstOrFail()->id, 'pricing_model' => Package::MODEL_PER_DAY, 'min_days' => 1, 'max_days' => null]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => $price, 'price_eur' => $priceEur]);

    return $package;
}

function addedRentalFields(): array
{
    return ['offer' => ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString(), 'has_valid_licence' => '1', 'has_international_permit' => '0']];
}

it('books any number of added packages in one checkout, for one customer', function (): void {
    Mail::fake();
    $first = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    $second = addablePackage('Car Daily', Package::KIND_VEHICLE, 30, 27);
    offerAirportToReview();

    $this->get('/en/booking/review')->assertOk()->assertSee('Add another package')->assertSee('Scooter Daily')->assertSee('Car Daily');

    $this->get("/en/booking/add/{$first->id}")->assertOk()->assertSee('Add Scooter Daily')->assertDontSee('name="offer[email]"', false);
    $this->post("/en/booking/add/{$first->id}", addedRentalFields())->assertRedirect('/en/booking/review');
    $this->post("/en/booking/add/{$second->id}", addedRentalFields())->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('Also in this booking')
        ->assertSee('$20.00 / €18.00') // scooter, 2 days
        ->assertSee('$60.00 / €54.00') // car, 2 days
        ->assertSee('$95.00 / €86.00') // with the $15 / €14 airport trip
        ->assertSee('Save $3.00 / €3.00 on Airport Car'); // the offer is still on the table

    $confirm = $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect(Booking::query()->count())->toBe(3)
        ->and(Customer::query()->count())->toBe(1);

    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();
    $scooter = Booking::query()->where('package_id', $first->id)->sole();
    $car = Booking::query()->where('package_id', $second->id)->sole();

    expect($confirm->headers->get('Location'))->toContain($airport->reference)
        ->and($airport->togetherBookings()->pluck('id')->all())->toEqualCanonicalizing([$scooter->id, $car->id])
        ->and($scooter->togetherBookings()->pluck('id')->all())->toEqualCanonicalizing([$airport->id, $car->id])
        ->and($car->togetherBookings()->pluck('id')->all())->toEqualCanonicalizing([$airport->id, $scooter->id]);

    $this->get($confirm->headers->get('Location'))
        ->assertOk()
        ->assertSee($scooter->reference)
        ->assertSee($car->reference)
        ->assertSee('$95.00 / €86.00');

    $this->get("/en/booking/confirmation/{$car->reference}")->assertOk()->assertSee($airport->reference)->assertSee($scooter->reference);

    expect((new BookingConfirmationMail($scooter))->locale('en')->render())->toContain($airport->reference)->toContain($car->reference);
    expect((new NewBookingPlacedMail($car))->render())->toContain($airport->reference)->toContain($scooter->reference);

    Mail::assertQueued(BookingConfirmationMail::class, 3);

    // A cancelled one is no longer owed.
    $car->update(['status' => Booking::STATUS_CANCELLED]);

    $this->get("/en/booking/confirmation/{$airport->reference}")->assertOk()->assertSee('$35.00 / €32.00');
});

it('gives a checkout one receipt, whichever booking it is opened from', function (): void {
    Mail::fake();
    config(['core.business.address' => '[Address pending]']);
    $extra = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    offerAirportToReview();
    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields());
    $this->post("/en/booking/add/{$extra->id}", addedRentalFields());
    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $receipt = fn (Booking $booking) => view('booking::pdf.receipt', ['booking' => $booking, 'statusQrCode' => ''])->render();
    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();
    $tukTuk = Booking::query()->where('package_id', $this->tukTuk->id)->sole();
    $scooter = Booking::query()->where('package_id', $extra->id)->sole();

    foreach ([$airport, $tukTuk, $scooter] as $booking) {
        expect($receipt($booking))
            ->toContain('Booking references')
            ->toContain($airport->reference)
            ->toContain($tukTuk->reference)
            ->toContain($scooter->reference)
            ->toContain('Airport Car')
            ->toContain('Tuk Tuk Daily')
            ->toContain('Scooter Daily')
            ->toContain('UL504')
            ->toContain('-$3.00 / -€3.00')
            ->toContain('$12.00 / €11.00')
            ->toContain('$60.00 / €54.00')
            ->toContain('$20.00 / €18.00')
            ->toContain('Total for all bookings')
            ->toContain('Balance due in total')
            ->toContain('$92.00 / €83.00') // $12 + $60 + $20
            ->toContain('Your driver will be there at the pickup time.')
            ->toContain('your driving licence when you collect your vehicle.')
            ->not->toContain('Address pending');

        $response = $this->get("/en/booking/confirmation/{$booking->reference}/receipt")->assertOk();

        expect(substr($response->getContent(), 0, 5))->toBe('%PDF-')
            ->and($response->headers->get('Content-Disposition'))->toContain("receipt-{$booking->reference}.pdf");
    }

    // A cancelled one stays listed, and is no longer owed.
    $scooter->update(['status' => Booking::STATUS_CANCELLED]);

    expect($receipt($airport->refresh()))->toContain($scooter->reference)->toContain('$72.00 / €65.00')->not->toContain('$92.00');

    // Money already paid comes off, and is recorded in USD alone.
    $tukTuk->update(['amount_paid' => 10]);

    expect($receipt($airport->refresh()))->toContain('$10.00')->toContain('$62.00')->not->toContain('$62.00 / ');

    $scooter->update(['status' => Booking::STATUS_CONFIRMED]);

    // The joined total is in the emails and the control panel too.
    expect((new BookingConfirmationMail($scooter))->locale('en')->render())
        ->toContain('$60.00 / €54.00')
        ->toContain('Total for all bookings')
        ->toContain('$92.00 / €83.00');

    expect((new NewBookingPlacedMail($airport))->render())
        ->toContain('Total for all bookings')
        ->toContain('$92.00');

    Permission::findOrCreate('bookings.view');
    $admin = User::factory()->create();
    $admin->givePermissionTo('bookings.view');

    $this->actingAs($admin)->get("/control-panel/bookings/{$tukTuk->id}")
        ->assertOk()
        ->assertSee('Total for all bookings')
        ->assertSee('$92.00');

    // A booking on its own keeps the plain receipt, fitting what was booked.
    $single = Booking::factory()->create(['customer_id' => $airport->customer_id, 'package_id' => $this->airport->id, 'transfer_direction' => 'arrival', 'flight_number' => 'UL504', 'transfer_address' => 'Beach Hotel']);

    expect($receipt($single))
        ->not->toContain('Booking references')
        ->not->toContain('Total for all bookings')
        ->toContain('Airport pickup')
        ->toContain('Your driver will be there at the pickup time.')
        ->not->toContain('driving licence');
});

// ---- The saving, booked in either order -------------------------------------

function tukTukToReview(): void
{
    test()->get('/en/booking/start?package='.test()->tukTuk->id);
    test()->post('/en/booking/start', ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString()]);
    test()->post('/en/booking/package', ['package_id' => test()->tukTuk->id]);
    test()->post('/en/booking/addons', []);
    test()->post('/en/booking/details', [
        'first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456',
        'nationality' => 'GB', 'passport_number' => 'P1234567', 'has_valid_licence' => '1', 'has_international_permit' => '0', 'marketing_opt_in' => '0',
    ]);
}

function airportTripFields(): array
{
    return ['offer' => [
        'start_date' => now()->addDays(10)->toDateString(), 'transfer_direction' => 'arrival', 'transfer_time' => '14:30',
        'flight_number' => 'UL 504', 'transfer_area_id' => test()->area->id, 'transfer_address' => 'Beach Hotel',
    ]];
}

it('gives the saving when the airport pickup is added after the tuk tuk', function (): void {
    Mail::fake();
    tukTukToReview();

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('Save $3.00 / €3.00 on Airport Car')
        ->assertSee('Add Airport Car and save $3.00 / €3.00');

    $this->get("/en/booking/add/{$this->airport->id}")->assertOk()->assertSee('You save $3.00 / €3.00 on Airport Car.');

    $this->post("/en/booking/add/{$this->airport->id}", airportTripFields())->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('-$3.00 / -€3.00')
        ->assertSee('$12.00 / €11.00')
        ->assertSee('$52.00 / €47.00') // $40 + $12
        ->assertDontSee('Add Airport Car and save');

    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();
    $tukTuk = Booking::query()->where('package_id', $this->tukTuk->id)->sole();

    expect((float) $airport->total_amount)->toBe(12.0)
        ->and($airport->totalEur())->toBe(11.0)
        ->and($airport->displayOfferDiscount())->toBe('-$3.00 / -€3.00')
        ->and((float) $tukTuk->total_amount)->toBe(40.0)
        ->and($tukTuk->displayOfferDiscount())->toBeNull();
});

it('gives the saving when both packages were added to a third, and takes it off with the package that earned it', function (): void {
    Mail::fake();
    $scooter = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    $scooter->update(['min_days' => 2]);

    test()->get('/en/booking/start?package='.$scooter->id);
    test()->post('/en/booking/start', ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString()]);
    test()->post('/en/booking/package', ['package_id' => $scooter->id]);
    test()->post('/en/booking/addons', []);
    test()->post('/en/booking/details', [
        'first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456',
        'nationality' => 'GB', 'passport_number' => 'P1234567', 'has_valid_licence' => '1', 'has_international_permit' => '0', 'marketing_opt_in' => '0',
    ]);

    $this->post("/en/booking/add/{$this->airport->id}", airportTripFields());
    $this->get('/en/booking/review')->assertOk()->assertSee('$15.00 / €14.00')->assertDontSee('-$3.00');

    $this->post("/en/booking/add/{$this->tukTuk->id}", tukTukOfferFields());
    $this->get('/en/booking/review')->assertOk()->assertSee('-$3.00 / -€3.00')->assertSee('$12.00 / €11.00');

    $this->post("/en/booking/add/{$this->tukTuk->id}/remove");
    $this->get('/en/booking/review')->assertOk()->assertSee('$15.00 / €14.00')->assertDontSee('-$3.00');
});

it('shows the new total before confirming when the saving on an added package stops', function (): void {
    Mail::fake();
    tukTukToReview();
    $this->post("/en/booking/add/{$this->airport->id}", airportTripFields());
    $this->get('/en/booking/review')->assertOk()->assertSee('$52.00 / €47.00');

    $this->offer->delete();

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertRedirect('/en/booking/review')
        ->assertSessionHasErrors('booking');

    expect(Booking::query()->count())->toBe(0);

    $this->get('/en/booking/review')->assertOk()->assertSee('$55.00 / €50.00')->assertDontSee('-$3.00');

    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect((float) Booking::query()->where('package_id', $this->airport->id)->sole()->total_amount)->toBe(15.0);
});

it('keeps the offer saving when another package is added too', function (): void {
    Mail::fake();
    $extra = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    offerAirportToReview();

    $this->post("/en/booking/offer/{$this->offer->id}", tukTukOfferFields());
    $this->post("/en/booking/add/{$extra->id}", addedRentalFields());

    $this->get('/en/booking/review')->assertOk()->assertSee('-$3.00 / -€3.00')->assertSee('$92.00 / €83.00'); // $12 + $60 + $20

    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();

    expect((float) $airport->total_amount)->toBe(12.0)
        ->and($airport->linkedBooking->package_id)->toBe($this->tukTuk->id)
        ->and($airport->togetherBookings())->toHaveCount(2);
});

it('offers only other packages on sale, and an offered one through its offer', function (): void {
    $hidden = addablePackage('Old Scooter', Package::KIND_VEHICLE, 10, 9);
    $hidden->update(['is_active' => false]);
    offerAirportToReview();

    $this->get('/en/booking/review')->assertOk()->assertDontSee('Old Scooter')->assertDontSee('Add another package');

    foreach ([$hidden, $this->airport, $this->tukTuk] as $notAddable) {
        $this->get("/en/booking/add/{$notAddable->id}")->assertRedirect('/en/booking/review');
    }
});

// ---- A length with no price is never booked for free ------------------------

it('will not add a package for longer than it has prices for', function (): void {
    $this->tukTuk->pricingTiers()->update(['max_days' => 7]);
    offerAirportToReview();

    $start = now()->addDays(10);
    $tooLong = ['offer' => ['start_date' => $start->toDateString(), 'end_date' => $start->copy()->addDays(19)->toDateString(), 'has_valid_licence' => '1', 'has_international_permit' => '0']];

    $this->post("/en/booking/offer/{$this->offer->id}", $tooLong)->assertSessionHasErrors('offer.end_date');
    expect(session('booking_flow.offer'))->toBeNull();

    $extra = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    $extra->pricingTiers()->update(['max_days' => 7]);

    $this->post("/en/booking/add/{$extra->id}", $tooLong)->assertSessionHasErrors('offer.end_date');
    expect(session('booking_flow.extras'))->toBeEmpty();

    // Up to the last priced day it is fine.
    $tooLong['offer']['end_date'] = $start->copy()->addDays(6)->toDateString();

    $this->post("/en/booking/add/{$extra->id}", $tooLong)->assertRedirect('/en/booking/review');
    $this->get('/en/booking/review')->assertOk()->assertSee('$70.00 / €63.00');
});

it('will not book a package on its own for longer than it has prices for', function (): void {
    $this->tukTuk->pricingTiers()->update(['max_days' => 7]);

    $this->get('/en/booking/start?package='.$this->tukTuk->id);
    $this->post('/en/booking/start', ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(29)->toDateString()]);

    $this->get('/en/booking/package')->assertOk()->assertDontSee('Tuk Tuk Daily');
    $this->post('/en/booking/package', ['package_id' => $this->tukTuk->id])->assertSessionHasErrors('package_id');
});

it('sends the customer back when the price for their length is removed before confirming', function (): void {
    tukTukToReview();
    $this->get('/en/booking/review')->assertOk()->assertSee('$40.00 / €36.00');

    $this->tukTuk->pricingTiers()->update(['min_days' => 3]);

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect('/en/booking/package');

    expect(Booking::query()->count())->toBe(0);
});

it('stops staff booking a length the package has no price for', function (): void {
    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo(['bookings.view', 'bookings.manage']);
    $this->tukTuk->pricingTiers()->update(['max_days' => 7]);

    $booking = ['package_id' => (string) $this->tukTuk->id, 'start_date' => now()->addDays(10)->toDateString(), 'full_name' => 'Walk In Wendy', 'email' => 'wendy@example.test'];

    $this->actingAs($admin)->post('/control-panel/bookings', [...$booking, 'end_date' => now()->addDays(29)->toDateString()])->assertSessionHasErrors('end_date');

    expect(Booking::query()->count())->toBe(0);

    $this->post('/control-panel/bookings', [...$booking, 'end_date' => now()->addDays(16)->toDateString()])->assertSessionHasNoErrors();

    expect((float) Booking::query()->sole()->total_amount)->toBe(140.0);
});

// ---- Adding surfing and stays -----------------------------------------------

function surfLesson(string $model = Package::MODEL_PER_PERSON): Package
{
    $package = addablePackage('Surf Lesson', Package::KIND_ACTIVITY, 40, 36);
    $package->update(['pricing_model' => $model]);

    return $package;
}

it('lists surfing and stays under their own headings, open from the start', function (): void {
    surfLesson();
    addablePackage('Beach Cabana', Package::KIND_STAY, 50, 45);
    offerAirportToReview();

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('<details class="add-package" open>', false)
        ->assertSeeInOrder([
            (string) ProductCategory::query()->where('kind', Package::KIND_STAY)->orderBy('sort_order')->firstOrFail()->name,
            'Beach Cabana',
            (string) ProductCategory::query()->where('kind', Package::KIND_ACTIVITY)->orderBy('sort_order')->firstOrFail()->name,
            'Surf Lesson',
        ]);
});

it('books surfing per person with the rest of the checkout, reserving no vehicle', function (): void {
    Mail::fake();
    $surf = surfLesson();
    $cabana = addablePackage('Beach Cabana', Package::KIND_STAY, 50, 45);
    offerAirportToReview();

    $this->get("/en/booking/add/{$surf->id}")
        ->assertOk()
        ->assertSee('Number of people')
        ->assertSee(__('core::front.booking_activity_note'))
        ->assertDontSee(__('core::front.booking_has_valid_licence'))
        ->assertDontSee('name="offer[end_date]"', false);

    $this->post("/en/booking/add/{$surf->id}", ['offer' => ['start_date' => now()->addDays(11)->toDateString(), 'people' => '3']])->assertRedirect('/en/booking/review');
    $this->post("/en/booking/add/{$cabana->id}", ['offer' => ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString()]])->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('3 people')
        ->assertSee('$120.00 / €108.00') // 3 × $40
        ->assertSee('$100.00 / €90.00') // 2 nights
        ->assertSee('$235.00 / €212.00'); // with the $15 / €14 airport trip

    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect(Booking::query()->count())->toBe(3);

    $lesson = Booking::query()->where('package_id', $surf->id)->sole();
    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();

    expect((float) $lesson->total_amount)->toBe(120.0)
        ->and($lesson->totalEur())->toBe(108.0)
        ->and($lesson->people())->toBe(3)
        ->and($lesson->vehicle_id)->toBeNull()
        ->and($lesson->has_international_permit)->toBeFalse()
        ->and($lesson->isActivity())->toBeTrue()
        ->and($lesson->rentalPeriod())->toBe(now()->addDays(11)->format('d M Y').' (3 people)')
        ->and($lesson->officeAddress())->toBeNull()
        ->and($airport->togetherBookings()->pluck('id')->all())->toContain($lesson->id);

    $this->get("/en/booking/confirmation/{$lesson->reference}")
        ->assertOk()
        ->assertSee('3 people')
        ->assertSee(__('core::front.booking_confirmation_next_steps_activity'))
        ->assertDontSee(__('core::front.booking_confirmation_next_steps'));

    expect(view('booking::pdf.receipt', ['booking' => $airport, 'statusQrCode' => ''])->render())
        ->toContain('Activity details')
        ->toContain('Surf Lesson')
        ->toContain('$120.00 / €108.00')
        ->toContain('$235.00 / €212.00')
        ->toContain(e(__('core::front.receipt_footer_activity')));

    expect((new BookingConfirmationMail($lesson))->locale('en')->render())
        ->toContain('Your activity is booked')
        ->toContain('3 people')
        ->not->toContain('driving licence');

    expect((new NewBookingPlacedMail($lesson))->render())->toContain('3 people')->not->toContain('International permit');

    $this->get("/en/booking/confirmation/{$lesson->reference}/receipt")->assertOk();

    Permission::findOrCreate('bookings.view');
    $admin = User::factory()->create();
    $admin->givePermissionTo('bookings.view');

    $this->actingAs($admin)->get("/control-panel/bookings/{$lesson->id}")->assertOk()->assertSee('3 people');
});

it('charges a fixed-price activity once, without asking how many people', function (): void {
    Mail::fake();
    $surf = surfLesson(Package::MODEL_FIXED_BUNDLE);
    offerAirportToReview();

    $this->get("/en/booking/add/{$surf->id}")->assertOk()->assertDontSee('Number of people');

    $this->post("/en/booking/add/{$surf->id}", ['offer' => ['start_date' => now()->addDays(11)->toDateString()]])->assertRedirect('/en/booking/review');
    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $lesson = Booking::query()->where('package_id', $surf->id)->sole();

    expect((float) $lesson->total_amount)->toBe(40.0)
        ->and($lesson->people())->toBeNull()
        ->and($lesson->rentalPeriod())->toBe(now()->addDays(11)->format('d M Y'));
});

it('asks how many people, within what the activity has a price for', function (): void {
    $surf = surfLesson();
    $surf->pricingTiers()->update(['max_days' => 4]);
    offerAirportToReview();

    $start = now()->addDays(11)->toDateString();

    $this->post("/en/booking/add/{$surf->id}", ['offer' => ['start_date' => $start]])->assertSessionHasErrors('offer.people');
    $this->post("/en/booking/add/{$surf->id}", ['offer' => ['start_date' => $start, 'people' => '0']])->assertSessionHasErrors('offer.people');
    $this->post("/en/booking/add/{$surf->id}", ['offer' => ['start_date' => $start, 'people' => '5']])->assertSessionHasErrors('offer.people');

    expect(session('booking_flow.extras'))->toBeEmpty();

    $this->post("/en/booking/add/{$surf->id}", ['offer' => ['start_date' => $start, 'people' => '4']])->assertRedirect('/en/booking/review');
    $this->get('/en/booking/review')->assertOk()->assertSee('$160.00 / €144.00');
});

it('keeps surfing on its own booked by message', function (): void {
    $surf = surfLesson();

    $this->get('/en/packages/'.$surf->slugFor('en'))->assertOk()->assertSee('wa.me');

    $this->get('/en/booking/start?package='.$surf->id)->assertOk();

    expect(session('booking_flow.package_id'))->toBeNull();
});

it('holds an added package to its own rules, and can take it off again', function (): void {
    $extra = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    $extra->update(['min_days' => 3]);
    offerAirportToReview();

    $this->post("/en/booking/add/{$extra->id}", addedRentalFields())->assertSessionHasErrors('offer.end_date');
    expect(session('booking_flow.extras'))->toBeEmpty();

    $this->post("/en/booking/add/{$extra->id}", ['offer' => [...addedRentalFields()['offer'], 'end_date' => now()->addDays(13)->toDateString()]])->assertRedirect('/en/booking/review');
    $this->get('/en/booking/review')->assertOk()->assertSee('Also in this booking');

    $this->post("/en/booking/add/{$extra->id}/remove")->assertRedirect('/en/booking/review');
    $this->get('/en/booking/review')->assertOk()->assertDontSee('Also in this booking');
});

it('takes off an added package that stopped being sold before confirming', function (): void {
    $extra = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    offerAirportToReview();
    $this->post("/en/booking/add/{$extra->id}", addedRentalFields());

    $extra->update(['is_active' => false]);

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertRedirect('/en/booking/review')
        ->assertSessionHasErrors('booking');

    expect(Booking::query()->count())->toBe(0);
    $this->get('/en/booking/review')->assertOk()->assertDontSee('Also in this booking');
});

it('takes off only the added package with nothing free, and books nothing until the new total is seen', function (): void {
    $free = addablePackage('Scooter Daily', Package::KIND_VEHICLE, 10, 9);
    $full = addablePackage('Beach Cabana', Package::KIND_STAY, 50, 45);
    $full->productCategory->update(['unlimited_units' => false]); // and no cabana exists
    offerAirportToReview();
    $this->post("/en/booking/add/{$free->id}", addedRentalFields());
    $this->post("/en/booking/add/{$full->id}", ['offer' => ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString()]])->assertRedirect('/en/booking/review');

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertRedirect('/en/booking/review')
        ->assertSessionHasErrors('booking');

    expect(Booking::query()->count())->toBe(0)
        ->and(array_keys(session('booking_flow.extras')))->toBe([$free->id]);

    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect(Booking::query()->count())->toBe(2);
});

it('drops an added package when it becomes the package being booked', function (): void {
    $extra = addablePackage('Scooter Daily', Package::KIND_TRANSFER, 10, 9);
    offerAirportToReview();
    $this->post("/en/booking/add/{$extra->id}", [
        'offer' => ['start_date' => now()->addDays(10)->toDateString(), 'transfer_direction' => 'arrival', 'transfer_time' => '14:30', 'flight_number' => 'UL 504', 'transfer_area_id' => $this->area->id, 'transfer_address' => 'Beach Hotel'],
    ])->assertRedirect('/en/booking/review');

    $this->post('/en/booking/package', ['package_id' => $extra->id])->assertRedirect('/en/booking/addons');

    expect(session('booking_flow.extras'))->toBe([]);
});

// ---- Staff ------------------------------------------------------------------

function staffOfferPayload(array $overrides = []): array
{
    return [
        'package_id' => (string) test()->airport->id,
        'start_date' => now()->addDays(10)->toDateString(),
        'transfer_direction' => 'arrival',
        'transfer_time' => '14:30',
        'flight_number' => 'UL 504',
        'transfer_area_id' => (string) test()->area->id,
        'transfer_address' => 'Beach Hotel',
        'full_name' => 'Walk In Wendy',
        'email' => 'wendy@example.test',
        'offer_id' => (string) test()->offer->id,
        'offer' => ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString()],
        ...$overrides,
    ];
}

it('lets staff apply the offer on a manual booking', function (): void {
    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    Permission::findOrCreate('customers.view');
    $admin = User::factory()->create();
    $admin->givePermissionTo(['bookings.view', 'bookings.manage', 'customers.view']);
    $this->actingAs($admin);

    $this->get('/control-panel/bookings/create')->assertOk()->assertSee('Tuk Tuk Daily — saves $3.00 / €3.00');

    $this->postJson('/control-panel/bookings/quote', staffOfferPayload())
        ->assertOk()
        ->assertJson(['total' => 12, 'offer_discount' => 3, 'display' => [
            'offer_discount' => '-$3.00 / -€3.00',
            'offered_name' => 'Tuk Tuk Daily',
            'offered_total' => '$60.00 / €54.00',
            'both_total' => '$72.00 / €65.00',
        ]]);

    expect(Booking::query()->count())->toBe(0);

    $this->post('/control-panel/bookings', staffOfferPayload())->assertSessionHasNoErrors()->assertRedirect();

    $airport = Booking::query()->where('package_id', $this->airport->id)->sole();
    $tukTuk = Booking::query()->where('package_id', $this->tukTuk->id)->sole();

    expect((float) $airport->total_amount)->toBe(12.0)
        ->and($airport->linked_booking_id)->toBe($tukTuk->id)
        ->and($tukTuk->customer_id)->toBe($airport->customer_id)
        ->and(Customer::query()->count())->toBe(1);

    $this->get("/control-panel/bookings/{$airport->id}")
        ->assertOk()
        ->assertSee('-$3.00 / -€3.00')
        ->assertSee($tukTuk->reference);

    $this->post('/control-panel/bookings', staffOfferPayload(['offer' => ['start_date' => now()->addDays(10)->toDateString()]]))
        ->assertSessionHasErrors('offer.end_date');

    $this->post('/control-panel/bookings', staffOfferPayload(['package_id' => (string) $this->tukTuk->id, 'end_date' => now()->addDays(12)->toDateString()]))
        ->assertSessionHasErrors('offer_id');
});

it('warns staff when the other booking was cancelled', function (): void {
    Permission::findOrCreate('bookings.view');
    $admin = User::factory()->create();
    $admin->givePermissionTo('bookings.view');

    $other = Booking::factory()->create(['customer_id' => Customer::factory(), 'status' => Booking::STATUS_CANCELLED, 'price_breakdown' => [], 'total_amount' => 60]);
    $booking = Booking::factory()->create(['customer_id' => $other->customer_id, 'linked_booking_id' => $other->id, 'price_breakdown' => ['offer_discount' => 3], 'total_amount' => 12]);

    $this->actingAs($admin)->get("/control-panel/bookings/{$booking->id}")
        ->assertOk()
        ->assertSee('The other booking was cancelled');
});

// ---- Package form -----------------------------------------------------------

it('saves offers on the package form, when creating and editing', function (): void {
    Permission::findOrCreate('packages.manage');
    Permission::findOrCreate('packages.view');
    $admin = User::factory()->create(['is_active' => true]);
    $admin->givePermissionTo(['packages.manage', 'packages.view']);
    $this->actingAs($admin);

    $category = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->first();
    $form = ['name' => ['en' => 'New Package'], 'product_category_id' => $category->id, 'charge' => 'per_day', 'price' => '30', 'price_eur' => '27', 'is_active' => '1'];

    $this->post('/control-panel/packages', [...$form, 'offers' => [$this->airport->id => ['discount' => '2', 'discount_eur' => '']]])
        ->assertSessionHasErrors('offers.'.$this->airport->id.'.discount_eur');

    $this->post('/control-panel/packages', [...$form, 'offers' => [$this->airport->id => ['discount' => '2', 'discount_eur' => '1.50']]])
        ->assertSessionHasNoErrors();

    $package = Package::query()->where('name->en', 'New Package')->firstOrFail();
    $offer = $package->offers()->sole();

    expect($offer->offered_package_id)->toBe($this->airport->id)
        ->and((float) $offer->discount_eur)->toBe(1.5);

    $this->get("/control-panel/packages/{$package->id}/edit")->assertOk()->assertSee('Special offers')->assertSee('value="1.50"', false);

    // A package can't be its own offer, and an emptied row removes the offer.
    $this->put("/control-panel/packages/{$package->id}", [...$form, 'offers' => [
        $package->id => ['discount' => '2', 'discount_eur' => '2'],
        $this->airport->id => ['discount' => '', 'discount_eur' => ''],
    ]])->assertSessionHasNoErrors();

    expect($package->offers()->count())->toBe(0);
});
