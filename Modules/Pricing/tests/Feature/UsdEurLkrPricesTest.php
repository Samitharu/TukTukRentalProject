<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Modules\Availability\Models\TransferArea;
use Modules\Booking\Mail\BookingConfirmationMail;
use Modules\Booking\Mail\NewBookingPlacedMail;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Modules\Pricing\Models\Currency;
use Modules\Pricing\Services\PricingService;
use Modules\Pricing\Support\Money;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    Permission::findOrCreate('pricing.manage');
    Permission::findOrCreate('packages.view');
    Permission::findOrCreate('packages.manage');
    Permission::findOrCreate('bookings.view');

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->givePermissionTo(['pricing.manage', 'packages.view', 'packages.manage', 'bookings.view']);

    $this->package = Package::factory()->create(['pricing_model' => Package::MODEL_PER_DAY]);
    $this->tier = $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 25, 'price_eur' => 23]);
    $this->start = CarbonImmutable::parse('2027-06-01');
});

function setLkrRate(float $rate, string $from = 'USD'): void
{
    Currency::query()
        ->firstOrCreate(['code' => 'LKR'], ['symbol' => 'Rs', 'is_base' => false, 'is_active' => true, 'decimal_places' => 0])
        ->exchangeRates()
        ->create(['from_code' => $from, 'rate' => $rate, 'fetched_at' => now()]);
}

// ---- Calculation ------------------------------------------------------------

it('totals EUR from the EUR prices staff entered, not from a conversion', function (): void {
    $addon = Addon::factory()->create(['price' => 5, 'price_eur' => 4.5, 'pricing_unit' => Addon::UNIT_PER_DAY, 'max_quantity' => 3]);
    $this->package->addons()->attach($addon);

    $price = app(PricingService::class)->calculate($this->package, $this->start, 3, [$addon->id => 2]);

    expect($price->total)->toBe(105.0) // 25 × 3 + 5 × 3 days × 2
        ->and($price->eur['base_amount'])->toBe(69.0)
        ->and($price->eur['addons_total'])->toBe(27.0)
        ->and($price->eur['total'])->toBe(96.0);
});

it('adds the airport area price in each currency', function (): void {
    $this->package->update(['kind' => Package::KIND_TRANSFER, 'pricing_model' => Package::MODEL_FIXED_BUNDLE]);
    $area = TransferArea::factory()->create(['price' => 40, 'price_eur' => 36]);

    $price = app(PricingService::class)->calculate($this->package, $this->start, 1, transferArea: $area);

    expect($price->total)->toBe(65.0)
        ->and($price->eur['total'])->toBe(59.0);
});

it('applies a percent season to both currencies and a fixed season from its EUR amount', function (): void {
    $season = $this->package->seasons()->create([
        'name' => 'Peak', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31',
        'price_modifier_type' => 'percent', 'price_modifier_value' => 10, 'weekday_mask' => 127, 'priority' => 1,
    ]);

    expect(app(PricingService::class)->calculate($this->package, $this->start, 2)->eur['total'])->toBe(50.6);

    $season->update(['price_modifier_type' => 'fixed', 'price_modifier_value' => 6, 'price_modifier_value_eur' => 5]);

    expect(app(PricingService::class)->calculate($this->package, $this->start, 2)->eur['total'])->toBe(51.0);

    $season->update(['price_modifier_value_eur' => null]);

    expect(app(PricingService::class)->calculate($this->package, $this->start, 2)->eur)->toBeNull();
});

it('gives no EUR total when any priced part has no EUR price', function (): void {
    $addon = Addon::factory()->create(['price' => 5, 'price_eur' => null]);
    $this->package->addons()->attach($addon);
    $pricing = app(PricingService::class);

    expect($pricing->calculate($this->package, $this->start, 2)->eur)->not->toBeNull()
        ->and($pricing->calculate($this->package, $this->start, 2, [$addon->id => 1])->eur)->toBeNull();

    $this->tier->update(['price_eur' => null]);

    expect($pricing->calculate($this->package, $this->start, 2)->eur)->toBeNull();
});

// ---- Display ----------------------------------------------------------------

it('writes amounts in each currency', function (): void {
    expect(Money::dual(25, 21.5))->toBe('$25.00 / €21.50')
        ->and(Money::dual(25, null))->toBe('$25.00')
        ->and(Money::dual(-5, -4))->toBe('-$5.00 / -€4.00')
        ->and(Money::admin(25, 21.5))->toBe('$25.00 / €21.50');

    setLkrRate(300);

    expect(Money::admin(25, 21.5))->toBe('$25.00 (LKR 7,500) / €21.50')
        ->and(Money::admin(25, null))->toBe('$25.00 (LKR 7,500)');

    setLkrRate(340, 'EUR');

    expect(Money::admin(25, 21.5))->toBe('$25.00 (LKR 7,500) / €21.50 (LKR 7,310)')
        ->and(Money::admin(25, null))->toBe('$25.00 (LKR 7,500)')
        ->and(Money::dual(25, 21.5))->toBe('$25.00 / €21.50');
});

it('shows customers USD and EUR but never LKR', function (): void {
    setLkrRate(300);

    $this->get('/en/pricing')
        ->assertOk()
        ->assertSee('$25.00 / €23.00')
        ->assertDontSee('LKR');
});

it('shows customers USD alone until a package has a EUR price', function (): void {
    $this->tier->update(['price_eur' => null]);

    $this->get('/en/pricing')
        ->assertOk()
        ->assertSee('$25.00')
        ->assertDontSee('€');
});

it('shows staff the booking total in USD, EUR and LKR, and customers without LKR', function (): void {
    setLkrRate(300);
    setLkrRate(350, 'EUR');

    $price = app(PricingService::class)->calculate($this->package, $this->start, 2);
    $booking = Booking::factory()->create([
        'customer_id' => Customer::factory(),
        'package_id' => $this->package->id,
        'price_breakdown' => $price->toArray(),
        'total_amount' => $price->total,
    ]);

    $this->actingAs($this->admin)
        ->get("/control-panel/bookings/{$booking->id}")
        ->assertOk()
        ->assertSee('$50.00 (LKR 15,000) / €46.00 (LKR 16,100)');

    expect((new NewBookingPlacedMail($booking))->render())->toContain('$50.00 (LKR 15,000) / €46.00 (LKR 16,100)');

    $customerEmail = (new BookingConfirmationMail($booking))->locale('en')->render();

    expect($customerEmail)->toContain('$50.00 / €46.00')
        ->and($customerEmail)->not->toContain('LKR');
});

it('shows both currencies through the customer booking and freezes the EUR amounts on it', function (): void {
    setLkrRate(300);

    $this->package->update(['min_days' => 1, 'max_days' => null]);
    $this->package->vehicles()->attach(Vehicle::factory()->create()->id);
    $addon = Addon::factory()->create(['is_active' => true, 'max_quantity' => 3, 'price' => 8, 'price_eur' => 7, 'pricing_unit' => Addon::UNIT_FLAT]);
    $this->package->addons()->attach($addon->id, ['is_included' => false]);

    $this->post('/en/booking/start', ['start_date' => '2027-06-01', 'end_date' => '2027-06-02', 'pickup_type' => 'office'])->assertRedirect('/en/booking/package');
    $this->get('/en/booking/package')->assertOk()->assertSee('$25.00 / €23.00');

    $this->post('/en/booking/package', ['package_id' => $this->package->id])->assertRedirect('/en/booking/addons');
    $this->get('/en/booking/addons')->assertOk()->assertSee('$8.00 / €7.00');

    $this->post('/en/booking/addons', ['addons' => [$addon->id => 2]])->assertRedirect('/en/booking/details');
    $this->post('/en/booking/details', [
        'first_name' => 'Jane', 'last_name' => 'Traveller', 'email' => 'jane@example.test', 'phone' => '+44 7911 123456',
        'nationality' => 'GB', 'passport_number' => 'P1234567', 'has_valid_licence' => '1', 'has_international_permit' => '1', 'marketing_opt_in' => '0',
    ])->assertRedirect('/en/booking/review');

    $this->get('/en/booking/review')
        ->assertOk()
        ->assertSee('$50.00 / €46.00') // rental: 2 days
        ->assertSee('$16.00 / €14.00') // 2 add-ons
        ->assertSee('$66.00 / €60.00') // total
        ->assertDontSee('LKR');

    $confirm = $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    $this->get($confirm->headers->get('Location'))->assertOk()->assertSee('$66.00 / €60.00')->assertDontSee('LKR');

    $booking = Booking::query()->sole();

    expect($booking->totalEur())->toBe(60.0)
        ->and((float) $booking->addons->first()->unit_price_eur)->toBe(7.0);

    // A later EUR price change leaves the booking alone.
    $this->tier->update(['price_eur' => 99]);

    expect($booking->fresh()->displayTotal())->toBe('$66.00 / €60.00');
});

it('keeps showing older bookings, which have no EUR amounts', function (): void {
    $booking = Booking::factory()->create(['customer_id' => Customer::factory(), 'price_breakdown' => [], 'total_amount' => 50]);

    expect($booking->displayTotal())->toBe('$50.00')
        ->and($booking->displayBalance())->toBe('$50.00');
});

it('charges extra km in both currencies at the rates frozen on the booking', function (): void {
    $booking = Booking::factory()->create([
        'customer_id' => Customer::factory(),
        'price_breakdown' => ['eur' => ['total' => 46]],
        'total_amount' => 50,
        'included_km' => 100,
        'extra_km_rate' => 0.5,
        'extra_km_rate_eur' => 0.4,
    ]);

    app(BookingService::class)->recordOdometer($booking, 1000, 1110);

    $fresh = $booking->fresh();

    expect((float) $fresh->extraCharges->first()->amount_eur)->toBe(4.0)
        ->and($fresh->adminTotalWithExtras())->toBe('$55.00 / €50.00');
});

// ---- Staff screens ----------------------------------------------------------

it('lets staff with permission set both LKR rates', function (): void {
    $this->actingAs($this->admin)
        ->post('/control-panel/currencies/rate', ['rate' => '300', 'rate_eur' => '350'])
        ->assertRedirect('/control-panel/currencies');

    expect(Currency::lkrPerUsd())->toBe(300.0)
        ->and(Currency::lkrPerEur())->toBe(350.0);

    $this->actingAs($this->admin)->post('/control-panel/currencies/rate', ['rate' => '310.50', 'rate_eur' => '360.25']);

    expect(Currency::lkrPerUsd())->toBe(310.5)
        ->and(Currency::lkrPerEur())->toBe(360.25);

    $this->actingAs($this->admin)->get('/control-panel/currencies')->assertOk()->assertSee('310.5')->assertSee('360.25');
});

it('rejects a bad or missing LKR rate and staff without permission', function (): void {
    $this->actingAs($this->admin)
        ->post('/control-panel/currencies/rate', ['rate' => '0', 'rate_eur' => '350'])
        ->assertSessionHasErrors('rate');

    $this->actingAs($this->admin)
        ->post('/control-panel/currencies/rate', ['rate' => '300'])
        ->assertSessionHasErrors('rate_eur');

    $this->actingAs(User::factory()->create(['is_active' => true]))
        ->post('/control-panel/currencies/rate', ['rate' => '300', 'rate_eur' => '350'])
        ->assertForbidden();

    expect(Currency::lkrPerUsd())->toBeNull()
        ->and(Currency::lkrPerEur())->toBeNull();
});

it('does not read an old EUR exchange rate as the LKR rate for EUR', function (): void {
    Currency::query()
        ->firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'is_base' => false, 'is_active' => true, 'decimal_places' => 2])
        ->exchangeRates()
        ->create(['rate' => 0.92, 'fetched_at' => now()]);

    expect(Currency::lkrPerEur())->toBeNull();
});

it('requires the EUR price on the package form and saves it', function (): void {
    $category = ProductCategory::query()->where('kind', Package::KIND_VEHICLE)->first();
    $form = ['name' => ['en' => 'Day Hire'], 'product_category_id' => $category->id, 'charge' => 'per_day', 'price' => '30', 'is_active' => '1'];

    $this->actingAs($this->admin)->post('/control-panel/packages', $form)->assertSessionHasErrors('price_eur');

    $this->actingAs($this->admin)->post('/control-panel/packages', [...$form, 'price_eur' => '27'])->assertSessionHasNoErrors();

    $tier = Package::query()->where('name->en', 'Day Hire')->firstOrFail()->pricingTiers()->first();

    expect((float) $tier->price)->toBe(30.0)
        ->and((float) $tier->price_eur)->toBe(27.0);
});

it('lets staff add a EUR price to an existing rental-length price', function (): void {
    $this->tier->update(['price_eur' => null]);
    $url = "/control-panel/packages/{$this->package->id}/pricing-tiers/{$this->tier->id}";

    $this->actingAs($this->admin)->get('/control-panel/packages')->assertSee('No EUR price yet');

    $this->actingAs($this->admin)->put($url, ['price' => '25'])->assertSessionHasErrors('price_eur');
    $this->actingAs($this->admin)->put($url, ['price' => '26', 'price_eur' => '24'])->assertSessionHasNoErrors();

    expect((float) $this->tier->fresh()->price)->toBe(26.0)
        ->and((float) $this->tier->fresh()->price_eur)->toBe(24.0);

    $this->actingAs($this->admin)->get('/control-panel/packages')->assertDontSee('No EUR price yet');
});

it('will not change a price through another package', function (): void {
    $other = Package::factory()->create();

    $this->actingAs($this->admin)
        ->put("/control-panel/packages/{$other->id}/pricing-tiers/{$this->tier->id}", ['price' => '1', 'price_eur' => '1'])
        ->assertNotFound();
});
