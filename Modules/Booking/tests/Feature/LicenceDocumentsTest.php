<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Booking\Mail\BookingConfirmationMail;
use Modules\Booking\Models\Booking;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * Tuk tuk rentals: after booking, the customer must send their driving
 * licence (by WhatsApp or email) so the business can arrange the Sri Lankan
 * permit. Set per package; the customer must agree before confirming.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    config(['core.business.whatsapp' => '94771234567', 'core.business.email' => 'hello@example.test', 'core.business.phone' => '+94 77 123 4567']);

    $this->vehicle = Vehicle::factory()->create();
    $this->package = Package::factory()->create(['name' => ['en' => 'Tuk Tuk Week'], 'min_days' => 1, 'max_days' => null, 'requires_licence_documents' => true, 'is_active' => true]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20, 'price_eur' => 18]);
    $this->package->vehicles()->attach($this->vehicle->id);
});

function reachLicenceReview(object $test, Package $package, int $drivers = 1): void
{
    $test->post('/en/booking/start', ['start_date' => '2027-01-10', 'end_date' => '2027-01-13'])->assertRedirect('/en/booking/package');
    $test->post('/en/booking/package', ['package_id' => $package->id])->assertRedirect('/en/booking/addons');
    $test->post('/en/booking/addons', [])->assertRedirect('/en/booking/details');
    $test->post('/en/booking/details', [
        'first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456',
        'nationality' => 'GB', 'passport_number' => 'P1234567', 'has_valid_licence' => '1', 'has_international_permit' => '1', 'marketing_opt_in' => '0',
        'drivers' => (string) $drivers,
    ])->assertRedirect('/en/booking/review');
}

// ---- Customer ----------------------------------------------------------------

it('tells customers on the package that every driver\'s licence is needed', function (): void {
    $this->get('/en/packages/'.$this->package->slugFor('en'))->assertOk()
        ->assertSee('Driving licence needed.')
        ->assertSee('front and back of the driving licence of each person who will drive');
});

it('asks how many people will drive, and needs an answer', function (): void {
    $this->package->update(['licence_fee' => 15, 'licence_fee_eur' => 14]);
    $this->post('/en/booking/start', ['start_date' => '2027-01-10', 'end_date' => '2027-01-13']);
    $this->post('/en/booking/package', ['package_id' => $this->package->id]);
    $this->post('/en/booking/addons', []);

    $this->get('/en/booking/details')->assertOk()
        ->assertSee('How many people will drive?')
        ->assertSee('name="drivers"', false)
        ->assertSee('Each driver needs a Sri Lankan driving permit ($15.00 / €14.00 per driver)');

    $details = ['first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456', 'nationality' => 'GB', 'passport_number' => 'P1', 'has_valid_licence' => '1', 'has_international_permit' => '1', 'marketing_opt_in' => '0'];

    $this->post('/en/booking/details', $details)->assertSessionHasErrors('drivers');
    $this->post('/en/booking/details', [...$details, 'drivers' => '7'])->assertSessionHasErrors('drivers');
    $this->post('/en/booking/details', [...$details, 'drivers' => '2'])->assertRedirect('/en/booking/review');
});

it('does not ask about drivers for a package that needs no licence', function (): void {
    $this->package->update(['requires_licence_documents' => false]);
    $this->post('/en/booking/start', ['start_date' => '2027-01-10', 'end_date' => '2027-01-13']);
    $this->post('/en/booking/package', ['package_id' => $this->package->id]);
    $this->post('/en/booking/addons', []);

    $this->get('/en/booking/details')->assertOk()->assertDontSee('name="drivers"', false);
});

it('will not book until the customer agrees to send their licence', function (): void {
    Mail::fake();
    reachLicenceReview($this, $this->package);

    $this->get('/en/booking/review')->assertOk()
        ->assertSee('name="licence_documents_accepted"', false)
        ->assertSee('I will send the driving licence of each driver by WhatsApp or email after booking.');

    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])
        ->assertSessionHasErrors(['licence_documents_accepted' => 'Please confirm that you will send your driving licence: we need it to arrange your Sri Lankan driving permit.']);
    expect(Booking::query()->count())->toBe(0);

    $this->post('/en/booking/confirm', ['terms_accepted' => '1', 'licence_documents_accepted' => '1'])->assertRedirect();

    $booking = Booking::query()->sole();
    expect($booking->licence_documents_required)->toBeTrue()
        ->and($booking->licence_documents_received_at)->toBeNull()
        ->and($booking->awaitsLicenceDocuments())->toBeTrue();
});

it('shows how to send the licence, by WhatsApp or email, after booking', function (): void {
    Mail::fake();
    reachLicenceReview($this, $this->package);
    $this->post('/en/booking/confirm', ['terms_accepted' => '1', 'licence_documents_accepted' => '1']);
    $booking = Booking::query()->sole();

    $this->get('/en/booking/confirmation/'.$booking->reference)->assertOk()
        ->assertSee('Next step: send your driving licence')
        ->assertSee('https://wa.me/94771234567?text='.rawurlencode('Hello, here is my driving licence for booking '.$booking->reference.'.'), false)
        ->assertSee('mailto:hello@example.test?subject='.rawurlencode('Driving licence for booking '.$booking->reference), false);

    $mail = (new BookingConfirmationMail($booking))->render();
    expect($mail)->toContain('Next step: send your driving licence')
        ->toContain('https://wa.me/94771234567')
        ->toContain('hello@example.test');

    expect(view('booking::pdf.receipt', ['booking' => $booking, 'statusQrCode' => ''])->render())
        ->toContain('Driving licence')
        ->toContain('Please send it by WhatsApp (+94 77 123 4567) or email (hello@example.test).');
});

it('asks nothing for a package that does not need a licence', function (): void {
    Mail::fake();
    $this->package->update(['requires_licence_documents' => false]);
    reachLicenceReview($this, $this->package);

    $this->get('/en/booking/review')->assertOk()->assertDontSee('name="licence_documents_accepted"', false);
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    $booking = Booking::query()->sole();
    expect($booking->licence_documents_required)->toBeFalse();
    $this->get('/en/booking/confirmation/'.$booking->reference)->assertOk()->assertDontSee('send your driving licence');
});

it('keeps what a booking asked for when the package changes later', function (): void {
    Mail::fake();
    reachLicenceReview($this, $this->package);
    $this->post('/en/booking/confirm', ['terms_accepted' => '1', 'licence_documents_accepted' => '1']);

    $this->package->update(['requires_licence_documents' => false]);

    expect(Booking::query()->sole()->fresh()->licence_documents_required)->toBeTrue();
});

// ---- Staff ---------------------------------------------------------------------

it('lets staff mark the licence received, which the customer then sees', function (): void {
    Mail::fake();
    $this->seed(AdminDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    reachLicenceReview($this, $this->package);
    $this->post('/en/booking/confirm', ['terms_accepted' => '1', 'licence_documents_accepted' => '1']);
    $booking = Booking::query()->sole();

    $this->actingAs($admin)->get('/control-panel/bookings')->assertOk()->assertSee('Licence pending');
    $this->actingAs($admin)->get('/control-panel/bookings/'.$booking->id)->assertOk()
        ->assertSee('not received yet')
        ->assertSee('Mark as received');

    $this->actingAs($admin)->put('/control-panel/bookings/'.$booking->id.'/licence', ['received' => '1'])->assertSessionHas('status');

    expect($booking->fresh()->licence_documents_received_at)->not->toBeNull()
        ->and($booking->fresh()->awaitsLicenceDocuments())->toBeFalse();

    $this->actingAs($admin)->get('/control-panel/bookings')->assertOk()->assertDontSee('Licence pending');
    $this->get('/en/booking/confirmation/'.$booking->reference)->assertOk()
        ->assertSee('We have received your driving licence. Thank you!')
        ->assertDontSee('Next step: send your driving licence');

    $this->actingAs($admin)->put('/control-panel/bookings/'.$booking->id.'/licence', ['received' => '0']);
    expect($booking->fresh()->licence_documents_received_at)->toBeNull();
});

it('keeps the licence action from staff without permission', function (): void {
    $booking = Booking::factory()->create(['package_id' => $this->package->id]);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->put('/control-panel/bookings/'.$booking->id.'/licence', ['received' => '1'])->assertForbidden();
    expect($booking->fresh()->licence_documents_received_at)->toBeNull();
});

it('lets staff set it on the package form, only for rentals', function (): void {
    $this->seed(AdminDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $tukTuks = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();
    $stays = ProductCategory::query()->where('kind', Package::KIND_STAY)->firstOrFail();
    $form = ['charge' => 'per_day', 'price' => '16', 'price_eur' => '14', 'is_active' => '1'];

    $this->actingAs($admin)->get('/control-panel/packages/create')->assertOk()
        ->assertSee('name="requires_licence_documents" value="1" checked', false);

    $this->actingAs($admin)->post('/control-panel/packages', [...$form, 'name' => ['en' => 'Self Drive'], 'product_category_id' => $tukTuks->id, 'requires_licence_documents' => '1'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/control-panel/packages', [...$form, 'name' => ['en' => 'With Driver'], 'product_category_id' => $tukTuks->id])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/control-panel/packages', [...$form, 'charge' => 'per_night', 'name' => ['en' => 'Cabana'], 'product_category_id' => $stays->id, 'requires_licence_documents' => '1'])->assertSessionHasNoErrors();

    $requires = fn (string $name) => Package::query()->where('name->en', $name)->sole()->requires_licence_documents;
    expect($requires('Self Drive'))->toBeTrue()
        ->and($requires('With Driver'))->toBeFalse()
        ->and($requires('Cabana'))->toBeFalse();
});

// ---- Permit fee ------------------------------------------------------------------

it('charges the permit fee for each driver, however long the booking is', function (int $days, int $drivers, float $fee, float $usd, float $eur): void {
    $this->package->update(['licence_fee' => 15, 'licence_fee_eur' => 14]);

    $price = app(Modules\Pricing\Services\PricingService::class)->calculate($this->package->fresh(), Carbon\CarbonImmutable::parse('2027-01-10'), $days, drivers: $drivers);

    expect($price->licenceFee)->toBe($fee)
        ->and($price->drivers)->toBe($drivers)
        ->and($price->total)->toBe($usd)
        ->and($price->eur['total'])->toBe($eur);
})->with([
    '1 day, 1 driver' => [1, 1, 15.0, 35.0, 32.0],
    '7 days, 1 driver' => [7, 1, 15.0, 155.0, 140.0],
    '1 day, 3 drivers' => [1, 3, 45.0, 65.0, 60.0],
]);

it('shows the permit fee per driver on the package, at checkout and on the receipt, and charges it', function (): void {
    Mail::fake();
    $this->package->update(['licence_fee' => 15, 'licence_fee_eur' => 14]);

    $this->get('/en/packages/'.$this->package->slugFor('en'))->assertOk()
        ->assertSee('Driving permit fee: $15.00 / €14.00 per driver, added to your booking.');

    reachLicenceReview($this, $this->package, drivers: 2);
    $this->get('/en/booking/review')->assertOk()
        ->assertSeeInOrder(['Sri Lankan driving permit × 2', '$30.00 / €28.00'])
        ->assertSee('$110.00 / €100.00'); // 4 days at $20 + 2 permits at $15

    $this->post('/en/booking/confirm', ['terms_accepted' => '1', 'licence_documents_accepted' => '1']);
    $booking = Booking::query()->sole();

    expect((float) $booking->total_amount)->toBe(110.0)
        ->and($booking->drivers())->toBe(2)
        ->and($booking->price_breakdown['licence_fee'])->toEqual(30)
        ->and($booking->totalEur())->toBe(100.0)
        ->and(view('booking::pdf.receipt', ['booking' => $booking, 'statusQrCode' => ''])->render())
        ->toContain('Sri Lankan driving permit × 2')->toContain('$30.00 / €28.00');
});

it('asks for the drivers when a licence package is added to another booking', function (): void {
    $this->package->update(['licence_fee' => 15, 'licence_fee_eur' => 14]);
    $package = $this->package->fresh();
    $data = ['start_date' => '2027-01-10', 'end_date' => '2027-01-11', 'drivers' => 3];

    expect(Modules\Booking\Services\OfferedBookingPlanner::rules($package))->toHaveKey('offer.drivers')
        ->and(app(Modules\Booking\Services\OfferedBookingPlanner::class)->plan($package, $data)['price']->licenceFee)->toBe(45.0);
});

it('charges no permit fee when the package does not need the licence', function (): void {
    $this->package->update(['requires_licence_documents' => false, 'licence_fee' => 15, 'licence_fee_eur' => 14]);

    $price = app(Modules\Pricing\Services\PricingService::class)->calculate($this->package->fresh(), Carbon\CarbonImmutable::parse('2027-01-10'), 2);

    expect($price->licenceFee)->toBe(0.0)->and($price->total)->toBe(40.0);
    $this->get('/en/packages/'.$this->package->slugFor('en'))->assertOk()->assertDontSee('Driving permit fee');
});

it('lets staff set the permit fee on the package, in both currencies', function (): void {
    $this->seed(AdminDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $line = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();
    $form = ['name' => ['en' => 'Self Drive'], 'product_category_id' => $line->id, 'charge' => 'per_day', 'price' => '16', 'price_eur' => '14', 'is_active' => '1', 'requires_licence_documents' => '1'];

    $this->actingAs($admin)->post('/control-panel/packages', [...$form, 'licence_fee' => '15'])
        ->assertSessionHasErrors(['licence_fee_eur' => 'Enter the permit fee in EUR too.']);

    $this->actingAs($admin)->post('/control-panel/packages', [...$form, 'licence_fee' => '15', 'licence_fee_eur' => '14'])->assertSessionHasNoErrors();

    $package = Package::query()->where('name->en', 'Self Drive')->sole();
    expect((float) $package->licence_fee)->toBe(15.0)->and((float) $package->licence_fee_eur)->toBe(14.0);

    $this->actingAs($admin)->get('/control-panel/packages/'.$package->id.'/edit')->assertOk()
        ->assertSee('name="licence_fee" value="15.00"', false);
});

it('includes the permit fee in a staff booking and its price preview', function (): void {
    $this->seed(AdminDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->package->update(['licence_fee' => 15, 'licence_fee_eur' => 14]);
    $form = ['package_id' => $this->package->id, 'start_date' => '2027-01-10', 'end_date' => '2027-01-11', 'full_name' => 'Walk In', 'email' => 'walkin@example.test'];

    $this->actingAs($admin)->postJson('/control-panel/bookings/quote', $form)->assertOk()
        ->assertJson(['licence_fee' => 15.0, 'total' => 55.0])
        ->assertJsonPath('display.licence', '$15.00 / €14.00');

    $this->actingAs($admin)->postJson('/control-panel/bookings/quote', [...$form, 'drivers' => 2])->assertOk()
        ->assertJson(['licence_fee' => 30.0, 'drivers' => 2, 'total' => 70.0]);

    $this->actingAs($admin)->post('/control-panel/bookings', [...$form, 'drivers' => 2])->assertSessionHasNoErrors();
    expect((float) Booking::query()->sole()->total_amount)->toBe(70.0)
        ->and(Booking::query()->sole()->drivers())->toBe(2);
});

it('turns it on for the existing tuk tuk packages when the update is installed', function (): void {
    $tukTuks = ProductCategory::query()->where('name->en', 'Tuk Tuk Rental')->firstOrFail();
    $stays = ProductCategory::query()->where('kind', Package::KIND_STAY)->firstOrFail();
    $tukTukPackage = Package::factory()->create(['product_category_id' => $tukTuks->id]);
    $stayPackage = Package::factory()->create(['product_category_id' => $stays->id]);

    // Run the update again, as on a server where these packages already exist.
    Schema::table('packages', fn ($table) => $table->dropColumn('requires_licence_documents'));
    (require base_path('Modules/Package/database/migrations/2026_11_10_000002_add_requires_licence_documents_to_packages_table.php'))->up();

    expect((bool) $tukTukPackage->fresh()->requires_licence_documents)->toBeTrue()
        ->and((bool) $stayPackage->fresh()->requires_licence_documents)->toBeFalse();
});
