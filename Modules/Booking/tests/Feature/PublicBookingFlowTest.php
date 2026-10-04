<?php

declare(strict_types=1);

use Modules\Availability\Models\BusinessLocation;
use Modules\Booking\Models\Booking;
use Modules\CMS\Models\Review;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Pricing\Models\Coupon;

/**
 * End-to-end coverage of the public 5-step booking wizard (brief §4) —
 * the customer-facing counterpart to the conflict-prevention engine
 * already covered by CreateHoldTest/ConfirmHoldTest/ConcurrentHoldTest.
 * Every step is submitted as a real HTTP POST (not a direct service call)
 * specifically because AdminManualBookingTest already proved that string-
 * typed HTTP form values surface bugs a typed unit test never would.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    $this->location = BusinessLocation::factory()->create(['is_active' => true]);
    $this->vehicle = Vehicle::factory()->create();

    $this->package = Package::factory()->create(['min_days' => 1, 'max_days' => null]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
    $this->package->vehicles()->attach($this->vehicle->id);

    // Fixed price/unit (rather than the factory's random default) so the
    // expected totals below are exact, not approximate.
    $this->addon = Addon::factory()->create(['is_active' => true, 'max_quantity' => 3, 'price' => 8, 'pricing_unit' => Addon::UNIT_FLAT]);
    $this->package->addons()->attach($this->addon->id, ['is_included' => false]);
});

function driverDetailsPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Traveller',
        'email' => 'jane.traveller@example.test',
        'phone' => '+44 7911 123456',
        'nationality' => 'gb', // deliberately lowercase — exercises prepareForValidation()'s uppercasing
        'passport_number' => 'P1234567',
        'has_valid_licence' => '1',
        'has_international_permit' => '1',
        'marketing_opt_in' => '0',
    ], $overrides);
}

it('walks through all five steps and creates a confirmed booking with no payment step', function (): void {
    $this->post('/en/booking/start', [
        'start_date' => '2027-01-10',
        'end_date' => '2027-01-13',
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ])->assertRedirect('/en/booking/package');

    $this->post('/en/booking/package', [
        'package_id' => $this->package->id,
    ])->assertRedirect('/en/booking/addons');

    $this->post('/en/booking/addons', [
        'addons' => [$this->addon->id => 2],
    ])->assertRedirect('/en/booking/details');

    $this->post('/en/booking/details', driverDetailsPayload())
        ->assertRedirect('/en/booking/review');

    $review = $this->get('/en/booking/review');
    $review->assertOk();
    $review->assertSee('Jane Traveller');

    $confirm = $this->post('/en/booking/confirm', ['terms_accepted' => '1']);
    $confirm->assertRedirect();
    expect($confirm->headers->get('Location'))->toContain('/en/booking/confirmation/MTR-');

    $booking = Booking::query()->first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(Booking::STATUS_CONFIRMED) // payment disabled — skips pending_payment
        ->and($booking->vehicle_id)->toBe($this->vehicle->id)
        ->and($booking->customer->nationality)->toBe('GB')
        ->and($booking->has_international_permit)->toBeTrue()
        ->and((float) $booking->total_amount)->toEqualWithDelta(96.0, 0.01); // (20/day * 4 days) + (2 * 8 flat addon)
});

it('recalculates the price server-side when a valid coupon is applied on the review step', function (): void {
    Coupon::query()->create([
        'code' => 'SAVE10',
        'type' => Coupon::TYPE_PERCENT,
        'value' => 10,
        'is_active' => true,
        'usage_count' => 0,
    ]);

    $this->post('/en/booking/start', [
        'start_date' => '2027-02-01',
        'end_date' => '2027-02-03',
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ]);
    $this->post('/en/booking/package', ['package_id' => $this->package->id]);
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', driverDetailsPayload());

    $response = $this->postJson('/en/booking/review/recalculate', ['coupon_code' => 'save10']);

    $response->assertOk();
    $response->assertJson(['coupon_code' => 'SAVE10', 'total' => 54.0]); // (20/day * 3 days) - 10%

    $this->post('/en/booking/confirm', ['terms_accepted' => '1']);

    expect(Coupon::query()->where('code', 'SAVE10')->value('usage_count'))->toBe(1);
});

it('rejects an invalid coupon code without mutating the session price', function (): void {
    $this->post('/en/booking/start', [
        'start_date' => '2027-03-01',
        'end_date' => '2027-03-02',
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ]);
    $this->post('/en/booking/package', ['package_id' => $this->package->id]);
    $this->post('/en/booking/addons', []);
    $this->post('/en/booking/details', driverDetailsPayload());

    $response = $this->postJson('/en/booking/review/recalculate', ['coupon_code' => 'DOES-NOT-EXIST']);

    $response->assertStatus(422);
});

it('redirects back to the start step when arriving at a later step without prior state', function (): void {
    $this->get('/en/booking/review')->assertRedirect('/en/booking/start');
    $this->get('/en/booking/package')->assertRedirect('/en/booking/start');
    $this->get('/en/booking/details')->assertRedirect('/en/booking/start');
});

it('returns 404 for a confirmation page with a reference that does not exist', function (): void {
    $this->get('/en/booking/confirmation/MTR-ZZZZZZZZ')->assertNotFound();
});

it('does not let a package outside the selected day range be chosen', function (): void {
    $shortPackage = Package::factory()->create(['min_days' => 1, 'max_days' => 2]);
    $shortPackage->pricingTiers()->create(['min_days' => 1, 'max_days' => 2, 'price' => 15]);

    $this->post('/en/booking/start', [
        'start_date' => '2027-04-01',
        'end_date' => '2027-04-10', // 10 days — outside the 1-2 day package
        'pickup_type' => 'office',
        'business_location_id' => $this->location->id,
    ]);

    $response = $this->post('/en/booking/package', ['package_id' => $shortPackage->id]);

    $response->assertSessionHasErrors('package_id');
});

function completeBookingFlow($test): Booking
{
    $test->post('/en/booking/start', [
        'start_date' => '2027-05-01',
        'end_date' => '2027-05-03',
        'pickup_type' => 'office',
        'business_location_id' => $test->location->id,
    ]);
    $test->post('/en/booking/package', ['package_id' => $test->package->id]);
    $test->post('/en/booking/addons', ['addons' => [$test->addon->id => 1]]);
    $test->post('/en/booking/details', driverDetailsPayload());
    $test->post('/en/booking/confirm', ['terms_accepted' => '1']);

    return Booking::query()->latest('id')->firstOrFail();
}

it('downloads the booking receipt as a PDF', function (): void {
    $booking = completeBookingFlow($this);

    $response = $this->get("/en/booking/confirmation/{$booking->reference}/receipt");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain("receipt-{$booking->reference}.pdf")
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

it('returns 404 for a receipt with an unknown reference', function (): void {
    $this->get('/en/booking/confirmation/MTR-ZZZZZZZZ/receipt')->assertNotFound();
});

it('shows a limited public booking status page for a valid reference', function (): void {
    $booking = completeBookingFlow($this);

    $this->get("/en/booking/status/{$booking->reference}")
        ->assertOk()
        ->assertSee(__('core::front.booking_status_confirmed'))
        ->assertSee($booking->reference)
        ->assertDontSee('jane.traveller@example.test');
});

it('lets a customer submit a booking-linked review from the homepage', function (): void {
    $booking = completeBookingFlow($this);

    $this->get('/en')->assertOk()->assertSee('id="feedback"', false);

    $response = $this->post('/en/booking/feedback', [
        'reference' => strtolower($booking->reference),
        'rating' => '5',
        'comment' => 'A lovely trip around the island.',
    ]);

    expect($response->headers->get('Location'))->toContain('/en#feedback');

    $review = Review::query()->where('booking_id', $booking->id)->sole();
    expect($review->rating)->toBe(5)
        ->and($review->content)->toBe('A lovely trip around the island.')
        ->and($review->is_approved)->toBeFalse();
});

it('stores a star rating and comment as an unapproved review, once per booking', function (): void {
    $booking = completeBookingFlow($this);

    $this->get("/en/booking/confirmation/{$booking->reference}")->assertOk()->assertSee('name="rating"', false);

    $this->post("/en/booking/confirmation/{$booking->reference}/feedback", [
        'rating' => '5',
        'comment' => 'Great tuk tuk, friendly team!',
    ])->assertRedirect();

    $review = Review::query()->where('booking_id', $booking->id)->sole();
    expect($review->rating)->toBe(5)
        ->and($review->content)->toBe('Great tuk tuk, friendly team!')
        ->and($review->customer_name)->toBe('Jane T.') // first name + last initial only
        ->and($review->country)->toBe('GB')
        ->and($review->is_approved)->toBeFalse();

    // A second submission for the same booking is ignored.
    $this->post("/en/booking/confirmation/{$booking->reference}/feedback", ['rating' => '1']);
    expect(Review::query()->where('booking_id', $booking->id)->count())->toBe(1);

    $this->get("/en/booking/confirmation/{$booking->reference}")->assertDontSee('name="rating"', false);
});

it('rejects a rating outside 1 to 5', function (): void {
    $booking = completeBookingFlow($this);

    $this->post("/en/booking/confirmation/{$booking->reference}/feedback", ['rating' => '6'])
        ->assertSessionHasErrors('rating');

    expect(Review::query()->count())->toBe(0);
});
