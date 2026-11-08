<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Booking\Models\Booking;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Modules\Pricing\Services\PricingService;

/**
 * Packages priced by group size, e.g. a lagoon tour per boat: 3–5 people
 * $10 an hour, 6–7 people $18 an hour. The group size picks the price;
 * an hourly package then multiplies it by the hours, never by the people.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->seed(AdminDatabaseSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->line = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->firstOrFail();
});

function lagoonTour(object $test, string $model = Package::MODEL_PER_HOUR, int $minHours = 2): Package
{
    $package = Package::factory()->create([
        'name' => ['en' => 'Lagoon Tour'],
        'product_category_id' => $test->line->id,
        'is_active' => true,
        'is_featured' => true,
        'pricing_model' => $model,
        'group_pricing' => true,
        'min_days' => 1,
        'max_days' => $model === Package::MODEL_PER_HOUR ? 1 : null,
        'min_hours' => $model === Package::MODEL_PER_HOUR ? $minHours : null,
        'max_hours' => $model === Package::MODEL_PER_HOUR ? 6 : null,
    ]);
    $package->pricingTiers()->create(['min_days' => 3, 'max_days' => 5, 'price' => 10, 'price_eur' => 9]);
    $package->pricingTiers()->create(['min_days' => 6, 'max_days' => 7, 'price' => 18, 'price_eur' => 16]);

    return $package->load('pricingTiers');
}

function groupFormPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Lagoon Tour'],
        'product_category_id' => $test->line->id,
        'charge' => 'per_hour',
        'min_hours' => '2',
        'max_hours' => '6',
        'group_pricing' => '1',
        'group_prices' => [
            ['from' => '3', 'to' => '5', 'price' => '10', 'price_eur' => '9'],
            ['from' => '6', 'to' => '7', 'price' => '18', 'price_eur' => '16'],
            ['from' => '', 'to' => '', 'price' => '', 'price_eur' => ''],
        ],
        'is_active' => '1',
    ], $overrides);
}

// ---- Price ---------------------------------------------------------------------

it('charges the group price per hour, not per person', function (int $people, int $hours, float $usd, float $eur): void {
    $price = app(PricingService::class)->calculate(lagoonTour($this), CarbonImmutable::today()->addDays(3), 1, hours: $hours, people: $people);

    expect($price->total)->toBe($usd)
        ->and($price->eur['total'])->toBe($eur)
        ->and($price->people)->toBe($people)
        ->and($price->hours)->toBe($hours);
})->with([
    '3 people, 2 hours' => [3, 2, 20.0, 18.0],
    '5 people, 2 hours' => [5, 2, 20.0, 18.0],
    '6 people, 3 hours' => [6, 3, 54.0, 48.0],
]);

it('charges a daily group-priced package per day, and a fixed one once', function (): void {
    $pricing = app(PricingService::class);
    $start = CarbonImmutable::today()->addDays(3);

    expect($pricing->calculate(lagoonTour($this, Package::MODEL_PER_DAY), $start, 3, people: 4)->total)->toBe(30.0)
        ->and($pricing->calculate(lagoonTour($this, Package::MODEL_FIXED_BUNDLE), $start, 1, people: 7)->total)->toBe(18.0);
});

// ---- What customers see -------------------------------------------------------

it('shows the smallest group\'s price for the minimum hours, and each group in the price table', function (): void {
    $package = lagoonTour($this);

    $this->get('/en')->assertOk()->assertSee('$20.00 / €18.00')->assertSee('for 2 hours')->assertSee('(3–5 people)', false);

    $this->get('/en/packages/'.$package->slugFor('en'))->assertOk()
        ->assertSeeInOrder(['3–5', 'people', '$20.00 / €18.00', 'for 2 hours', '$10.00 / €9.00', '/ hour'])
        ->assertSeeInOrder(['6–7', 'people', '$36.00 / €32.00', 'for 2 hours', '$18.00 / €16.00', '/ hour']);
});

// ---- Online booking -------------------------------------------------------------

function startGroupBooking(object $test, Package $package): void
{
    $date = now()->addDays(5)->toDateString();
    $test->get('/en/booking/start?package='.$package->id)->assertOk();
    $test->post('/en/booking/start', ['start_date' => $date, 'end_date' => $date])->assertRedirect();
}

it('asks how many people, books the matching group price, and shows the group size on the booking', function (): void {
    Mail::fake();
    $package = lagoonTour($this);
    startGroupBooking($this, $package);

    $this->get('/en/booking/package')->assertOk()
        ->assertSee('name="people['.$package->id.']"', false)
        ->assertSee('Number of people: 3')
        ->assertSee('Number of people: 7')
        ->assertDontSee('Number of people: 2')
        ->assertDontSee('Number of people: 8');

    $this->post('/en/booking/package', [
        'package_id' => $package->id,
        'people' => [$package->id => 6],
        'hourly' => [$package->id => ['start_time' => '09:00', 'hours' => 3]],
    ])->assertRedirect('/en/booking/addons');

    $this->post('/en/booking/addons', [])->assertRedirect('/en/booking/details');
    $this->post('/en/booking/details', [
        'first_name' => 'Ana', 'last_name' => 'Silva', 'email' => 'ana@example.test', 'phone' => '+351 912 345 678',
        'nationality' => 'PT', 'passport_number' => 'P1234567', 'has_valid_licence' => '1', 'has_international_permit' => '1', 'marketing_opt_in' => '0',
    ])->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')->assertOk()->assertSee('$54.00');
    $this->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();

    $booking = Booking::query()->where('package_id', $package->id)->sole();

    expect((float) $booking->total_amount)->toBe(54.0)
        ->and($booking->people())->toBe(6)
        ->and($booking->rentalPeriod())->toContain('3 hours, 6 people');
});

it('refuses a group size that has no price', function (int $people): void {
    $package = lagoonTour($this);
    startGroupBooking($this, $package);

    $this->post('/en/booking/package', [
        'package_id' => $package->id,
        'people' => [$package->id => $people],
        'hourly' => [$package->id => ['start_time' => '09:00', 'hours' => 2]],
    ])->assertSessionHasErrors('package_id');
})->with(['too few' => [2], 'too many' => [8], 'none chosen' => [0]]);

// ---- Control panel ------------------------------------------------------------------

it('lets staff set prices by group size, and ignores the empty rows', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/packages', groupFormPayload($this))->assertSessionHasNoErrors();

    $package = Package::query()->where('name->en', 'Lagoon Tour')->sole();

    expect($package->group_pricing)->toBeTrue()
        ->and($package->isGroupPriced())->toBeTrue()
        ->and($package->pricingTiers()->get(['min_days', 'max_days', 'price'])->map(fn ($tier) => [$tier->min_days, $tier->max_days, (float) $tier->price])->all())
        ->toBe([[3, 5, 10.0], [6, 7, 18.0]]);

    $this->actingAs($this->admin)->get('/control-panel/packages/'.$package->id.'/edit')->assertOk()
        ->assertSee('name="group_prices[1][from]" value="6"', false);
});

it('refuses group sizes that overlap, are incomplete, or missing', function (array $rows, string $message): void {
    $this->actingAs($this->admin)
        ->post('/control-panel/packages', groupFormPayload($this, ['group_prices' => $rows]))
        ->assertSessionHasErrors('group_prices');

    expect(session('errors')->first('group_prices'))->toContain($message)
        ->and(Package::query()->where('name->en', 'Lagoon Tour')->exists())->toBeFalse();
})->with([
    'overlap at 5' => [[['from' => '3', 'to' => '5', 'price' => '10', 'price_eur' => '9'], ['from' => '5', 'to' => '7', 'price' => '18', 'price_eur' => '16']], 'overlap'],
    'to below from' => [[['from' => '5', 'to' => '3', 'price' => '10', 'price_eur' => '9']], 'at least'],
    'price missing' => [[['from' => '3', 'to' => '5', 'price' => '10', 'price_eur' => '']], 'every box'],
    'no rows' => [[['from' => '', 'to' => '', 'price' => '', 'price_eur' => '']], 'at least one'],
]);

it('goes back to one price when group pricing is turned off', function (): void {
    $package = lagoonTour($this);

    $this->actingAs($this->admin)->put('/control-panel/packages/'.$package->id, groupFormPayload($this, [
        'group_pricing' => '0',
        'price' => '8',
        'price_eur' => '7',
    ]))->assertSessionHasNoErrors();

    $package->refresh()->load('pricingTiers');

    expect($package->group_pricing)->toBeFalse()
        ->and($package->pricingTiers->map(fn ($tier) => [$tier->min_days, $tier->max_days, (float) $tier->price])->all())->toBe([[1, null, 8.0]]);
});

it('ignores the group tick box for a way of charging that cannot use it', function (): void {
    $stays = ProductCategory::query()->where('kind', Package::KIND_STAY)->firstOrFail();

    $this->actingAs($this->admin)->post('/control-panel/packages', groupFormPayload($this, [
        'name' => ['en' => 'Beach Cabana'],
        'product_category_id' => $stays->id,
        'charge' => 'per_night',
        'price' => '40',
        'price_eur' => '36',
    ]))->assertSessionHasNoErrors();

    expect(Package::query()->where('name->en', 'Beach Cabana')->sole()->group_pricing)->toBeFalse();
});

it('makes staff enter the group size on a manual booking', function (): void {
    $package = lagoonTour($this);
    $date = now()->addDays(4)->toDateString();
    $form = ['package_id' => $package->id, 'start_date' => $date, 'end_date' => $date, 'start_time' => '09:00', 'hours' => 2, 'full_name' => 'Ana Silva', 'email' => 'ana@example.test'];

    $this->actingAs($this->admin)->post('/control-panel/bookings', $form)->assertSessionHasErrors('people');
    $this->actingAs($this->admin)->post('/control-panel/bookings', [...$form, 'people' => 9])->assertSessionHasErrors('people');

    $this->actingAs($this->admin)->post('/control-panel/bookings', [...$form, 'people' => 4])->assertSessionHasNoErrors();

    $booking = Booking::query()->where('package_id', $package->id)->sole();

    expect((float) $booking->total_amount)->toBe(20.0)->and($booking->people())->toBe(4);
});

it('asks for the group size when the package is added to another booking', function (): void {
    $package = lagoonTour($this);
    $planner = app(Modules\Booking\Services\OfferedBookingPlanner::class);
    $data = ['start_date' => now()->addDays(4)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(), 'start_time' => '09:00', 'hours' => 2];

    expect(Modules\Booking\Services\OfferedBookingPlanner::rules($package))->toHaveKey('offer.people')
        ->and(Modules\Booking\Services\OfferedBookingPlanner::problems($package, [...$data, 'people' => 8]))->toHaveKey('offer.people')
        ->and(Modules\Booking\Services\OfferedBookingPlanner::problems($package, [...$data, 'people' => 4]))->toBe([])
        ->and($planner->plan($package, [...$data, 'people' => 4])['price']->total)->toBe(20.0);
});

it('leaves existing packages priced as before', function (): void {
    $package = Package::factory()->create(['product_category_id' => $this->line->id, 'pricing_model' => Package::MODEL_PER_HOUR, 'min_hours' => 2, 'max_hours' => 6, 'min_days' => 1, 'max_days' => 1]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 6, 'price_eur' => 5]);

    expect($package->isGroupPriced())->toBeFalse()
        ->and(app(PricingService::class)->calculate($package, CarbonImmutable::today()->addDays(3), 1, hours: 3, people: 9)->total)->toBe(18.0);
});
