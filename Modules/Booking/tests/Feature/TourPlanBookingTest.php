<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Booking\Models\Booking;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * A "Tour plan" category: customers pick the first and last day online and
 * pay the package's price per day. Our driver runs it, so no unit is
 * reserved and no driving licence is asked for.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->seed(AdminDatabaseSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    // A registered tuk tuk exists: a tour must still never reserve it.
    $this->tukTuk = Vehicle::factory()->create();
});

it('lets an Admin create a tour plan category and a per-day tour package', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/package-categories', [
        'name' => ['en' => 'Tour Plans'],
        'kind' => Package::KIND_TOUR,
    ])->assertSessionHasNoErrors()->assertRedirect('/control-panel/package-categories');

    $line = ProductCategory::query()->where('name->en', 'Tour Plans')->firstOrFail();
    expect($line->kind)->toBe(Package::KIND_TOUR);

    $this->actingAs($this->admin)->get('/control-panel/packages/create?category='.$line->id)->assertOk()->assertSee('Per day');

    $this->actingAs($this->admin)->post('/control-panel/packages', [
        'name' => ['en' => 'Hill Country Tour'],
        'product_category_id' => $line->id,
        'charge' => 'per_day',
        'price' => '45',
        'price_eur' => '41',
        'min_days' => '2',
    ])->assertSessionHasNoErrors();

    $package = Package::query()->where('name->en', 'Hill Country Tour')->firstOrFail();

    expect($package->kind)->toBe(Package::KIND_TOUR)
        ->and($package->isTour())->toBeTrue()
        ->and($package->pricing_model)->toBe(Package::MODEL_PER_DAY)
        ->and($package->min_days)->toBe(2)
        ->and($package->requires_licence_documents)->toBeFalse()
        ->and($package->isBookableOnline())->toBeTrue()
        ->and($package->eligibleVehicleIds())->toBe([]);
});

it('rejects a per-hour charge for a tour package', function (): void {
    $line = ProductCategory::query()->create(['kind' => Package::KIND_TOUR, 'name' => ['en' => 'Tour Plans'], 'is_active' => true]);

    $this->actingAs($this->admin)->post('/control-panel/packages', [
        'name' => ['en' => 'Short Tour'],
        'product_category_id' => $line->id,
        'charge' => 'per_hour',
        'price' => '10',
        'price_eur' => '9',
    ])->assertSessionHasErrors('charge');
});

it('books a tour online by the day, with no licence questions and no unit reserved', function (): void {
    $line = ProductCategory::query()->create(['kind' => Package::KIND_TOUR, 'name' => ['en' => 'Tour Plans'], 'is_active' => true, 'unlimited_units' => false]);
    $tour = Package::factory()->create(['name' => ['en' => 'Hill Country Tour'], 'product_category_id' => $line->id, 'pricing_model' => Package::MODEL_PER_DAY, 'min_days' => 1, 'max_days' => null]);
    $tour->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 45]);

    $this->get('/en/booking/start?package='.$tour->id)
        ->assertOk()
        ->assertSee('When is your tour?')
        ->assertSee('First day')
        ->assertDontSee('Collect from our office');

    $this->post('/en/booking/start', ['start_date' => '2027-03-01', 'end_date' => '2027-03-03'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/en/booking/package');
    $this->get('/en/booking/package')->assertOk()->assertSee('Hill Country Tour');
    $this->post('/en/booking/package', ['package_id' => $tour->id])->assertRedirect('/en/booking/addons');
    $this->post('/en/booking/addons', [])->assertRedirect('/en/booking/details');

    $this->get('/en/booking/details')->assertOk()->assertDontSee('name="has_valid_licence"', false);
    // No licence/permit fields posted, and none required.
    $this->post('/en/booking/details', [
        'first_name' => 'Ana',
        'last_name' => 'Traveller',
        'email' => 'ana.traveller@example.test',
        'phone' => '+49 151 0000000',
        'nationality' => 'DE',
        'passport_number' => 'C01X00T47',
        'marketing_opt_in' => '0',
    ])->assertSessionHasNoErrors()->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')->assertOk();
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertSessionHasNoErrors()->assertRedirect();

    $booking = Booking::query()->firstOrFail();

    expect($booking->isTour())->toBeTrue()
        ->and($booking->vehicle_id)->toBeNull()
        ->and($booking->start_at->toDateString())->toBe('2027-03-01')
        ->and($booking->end_at->toDateString())->toBe('2027-03-03')
        ->and($booking->officeAddress())->toBeNull()
        ->and((float) $booking->total_amount)->toEqualWithDelta(135.0, 0.01) // 3 days × 45
        ->and(VehicleReservationSlot::query()->count())->toBe(0);

    $this->get('/en/booking/confirmation/'.$booking->reference)->assertOk();
    $this->get('/en/booking/confirmation/'.$booking->reference.'/receipt')->assertOk();
});
