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
        ->assertSee('Add Tuk Tuk Daily and save $3.00 / €3.00 on Airport Car.')
        ->assertSee('$15.00 / €14.00');

    $this->tukTuk->update(['is_active' => false]);

    $this->get('/en/booking/review')->assertOk()->assertDontSee('Add Tuk Tuk Daily');
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
        ->assertDontSee('Add to my booking');

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
    $this->get('/en/booking/review')->assertOk()->assertSee('$40.00 / €36.00')->assertSee('Add to my booking');

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
