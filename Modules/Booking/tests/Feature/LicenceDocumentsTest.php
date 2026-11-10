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

function reachLicenceReview(object $test, Package $package): void
{
    $test->post('/en/booking/start', ['start_date' => '2027-01-10', 'end_date' => '2027-01-13'])->assertRedirect('/en/booking/package');
    $test->post('/en/booking/package', ['package_id' => $package->id])->assertRedirect('/en/booking/addons');
    $test->post('/en/booking/addons', [])->assertRedirect('/en/booking/details');
    $test->post('/en/booking/details', [
        'first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456',
        'nationality' => 'GB', 'passport_number' => 'P1234567', 'has_valid_licence' => '1', 'has_international_permit' => '1', 'marketing_opt_in' => '0',
    ])->assertRedirect('/en/booking/review');
}

// ---- Customer ----------------------------------------------------------------

it('tells customers on the package that their driving licence is needed', function (): void {
    $this->get('/en/packages/'.$this->package->slugFor('en'))->assertOk()
        ->assertSee('Driving licence needed.')
        ->assertSee('front and back of your driving licence by WhatsApp or email');
});

it('will not book until the customer agrees to send their licence', function (): void {
    Mail::fake();
    reachLicenceReview($this, $this->package);

    $this->get('/en/booking/review')->assertOk()
        ->assertSee('name="licence_documents_accepted"', false)
        ->assertSee('I will send my driving licence by WhatsApp or email after booking.');

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
