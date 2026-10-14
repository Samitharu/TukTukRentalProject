<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Admin\Models\BlockedIp;
use Modules\Availability\Models\BusinessLocation;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\BookingFingerprint;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Spatie\Permission\Models\Permission;

/**
 * Spam-booking defences (a competitor blocking the fleet with fake, free
 * bookings): every public booking records where it came from, staff see
 * related bookings flagged, can cancel a batch at once, and can block an IP
 * from booking online. Nothing is rejected automatically.
 */
function spamPublicBooking(string $ip, array $details = []): \Illuminate\Testing\TestResponse
{
    $test = test()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', 'SpamBot/1.0');

    $test->post('/en/booking/start', [
        'start_date' => '2027-01-10',
        'end_date' => '2027-01-13',
        'pickup_type' => 'office',
        'business_location_id' => test()->location->id,
    ]);
    $test->post('/en/booking/package', ['package_id' => test()->package->id]);
    $test->post('/en/booking/addons', ['addons' => []]);
    $test->post('/en/booking/details', array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Traveller',
        'email' => 'jane.traveller@example.test',
        'phone' => '+44 7911 123456',
        'nationality' => 'GB',
        'passport_number' => 'p123 4567',
        'has_valid_licence' => '1',
        'has_international_permit' => '1',
        'marketing_opt_in' => '0',
    ], $details));

    return $test->post('/en/booking/confirm', ['terms_accepted' => '1']);
}

function upcomingBooking(array $attributes = []): Booking
{
    return Booking::factory()->create(array_merge([
        'start_at' => now()->addDays(5)->startOfDay(),
        'end_at' => now()->addDays(7)->startOfDay(),
    ], $attributes));
}

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    $this->location = BusinessLocation::factory()->create(['is_active' => true]);
    $this->vehicle = Vehicle::factory()->create();
    $this->package = Package::factory()->create(['min_days' => 1, 'max_days' => null]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
    $this->package->vehicles()->attach($this->vehicle->id);

    Permission::findOrCreate('bookings.view');
    Permission::findOrCreate('bookings.manage');
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['bookings.view', 'bookings.manage']);
});

it('normalizes phone numbers so local and international formats match', function (): void {
    expect(BookingFingerprint::phoneKey('+94 77 123 4567'))->toBe(BookingFingerprint::phoneKey('077-123-4567'))
        ->and(BookingFingerprint::phoneKey('12'))->toBeNull()
        ->and(BookingFingerprint::passportKey('n 123-456'))->toBe(BookingFingerprint::passportKey('N123456'))
        ->and(BookingFingerprint::passportKey(''))->toBeNull();
});

it('records the source of a public booking without storing the passport number in plain text', function (): void {
    spamPublicBooking('203.0.113.9')->assertRedirect();

    $booking = Booking::query()->sole();

    expect($booking->ip_address)->toBe('203.0.113.9')
        ->and($booking->user_agent)->toBe('SpamBot/1.0')
        ->and($booking->phone_key)->toBe('911123456')
        ->and($booking->passport_key)->toBe(BookingFingerprint::passportKey('P1234567'))
        ->and($booking->passport_key)->not->toContain('1234567');
});

it('does not record the staff IP on admin manual bookings', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/bookings', [
        'package_id' => $this->package->id,
        'vehicle_id' => (string) $this->vehicle->id,
        'start_date' => '2027-02-01',
        'end_date' => '2027-02-03',
        'pickup_type' => 'office',
        'full_name' => 'Walk In',
        'email' => 'walk-in@example.test',
        'phone' => '0771234567',
    ])->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();

    expect($booking->ip_address)->toBeNull()
        ->and($booking->phone_key)->toBe('771234567');
});

it('stops a blocked IP from confirming a booking and points the visitor to staff', function (): void {
    BlockedIp::query()->create(['ip' => '203.0.113.9', 'blocked_until' => now()->addDays(30)]);

    spamPublicBooking('203.0.113.9')
        ->assertRedirect('/en/booking/review')
        ->assertSessionHasErrors(['booking' => __('core::front.booking_blocked')]);

    expect(Booking::query()->count())->toBe(0);
});

it('lets an IP book again once its block has expired', function (): void {
    BlockedIp::query()->create(['ip' => '203.0.113.9', 'blocked_until' => now()->subMinute()]);

    spamPublicBooking('203.0.113.9')->assertSessionHasNoErrors();

    expect(Booking::query()->count())->toBe(1);
});

it('does not flag a traveller with two upcoming bookings, but flags three', function (): void {
    $customer = Customer::factory()->create();
    $first = upcomingBooking(['customer_id' => $customer->id]);
    upcomingBooking(['customer_id' => $customer->id, 'start_at' => now()->addDays(10), 'end_at' => now()->addDays(11)]);

    $this->actingAs($this->admin)->get('/control-panel/bookings')
        ->assertOk()
        ->assertDontSee('⚠ Suspicious', false);

    upcomingBooking(['customer_id' => $customer->id, 'start_at' => now()->addDays(20), 'end_at' => now()->addDays(21)]);

    $this->get('/control-panel/bookings')->assertSee('⚠ Suspicious', false);
    $this->get("/control-panel/bookings/{$first->id}")->assertSee('Possible spam booking');
});

it('flags bookings sharing an IP or phone even under different customers', function (): void {
    $a = upcomingBooking(['ip_address' => '198.51.100.7']);
    upcomingBooking(['ip_address' => '198.51.100.7']);
    upcomingBooking(['ip_address' => '198.51.100.7']);
    $b = upcomingBooking(['phone_key' => '771234567']);
    upcomingBooking(['phone_key' => '771234567']);
    upcomingBooking(['phone_key' => '771234567']);
    $genuine = upcomingBooking(['ip_address' => '192.0.2.1']);

    $flags = app(\Modules\Booking\Services\SuspiciousBookingDetector::class)->flagsFor([$a, $b, $genuine]);

    expect($flags)->toHaveKeys([$a->id, $b->id])
        ->and($flags)->not->toHaveKey($genuine->id)
        ->and($flags[$a->id])->toBe(['Same IP address']);
});

it('ignores cancelled and past bookings when counting', function (): void {
    $booking = upcomingBooking(['ip_address' => '198.51.100.7']);
    upcomingBooking(['ip_address' => '198.51.100.7', 'status' => Booking::STATUS_CANCELLED]);
    upcomingBooking(['ip_address' => '198.51.100.7', 'start_at' => now()->subDays(10), 'end_at' => now()->subDays(8)]);

    expect(app(\Modules\Booking\Services\SuspiciousBookingDetector::class)->flagsFor([$booking]))->toBe([]);
});

it('filters the bookings list to suspicious ones only', function (): void {
    $spam = collect(range(1, 3))->map(fn () => upcomingBooking(['ip_address' => '198.51.100.7']));
    $genuine = upcomingBooking(['ip_address' => '192.0.2.1']);

    $response = $this->actingAs($this->admin)->get('/control-panel/bookings?suspicious=1')->assertOk();

    foreach ($spam as $booking) {
        $response->assertSee($booking->reference);
    }
    $response->assertDontSee($genuine->reference);
});

it('cancels only the ticked related bookings', function (): void {
    $booking = upcomingBooking(['ip_address' => '198.51.100.7']);
    $related = upcomingBooking(['ip_address' => '198.51.100.7']);
    $keep = upcomingBooking(['ip_address' => '198.51.100.7']);
    $unrelated = upcomingBooking(['ip_address' => '192.0.2.1']);

    $this->actingAs($this->admin)->get("/control-panel/bookings/{$booking->id}")
        ->assertOk()
        ->assertSee($related->reference)
        ->assertSee($keep->reference)
        ->assertDontSee($unrelated->reference);

    $this->post("/control-panel/bookings/{$booking->id}/cancel-related", [
        'booking_ids' => [$booking->id, $related->id, $unrelated->id], // a tampered id is ignored
        'reason' => 'Spam booking',
    ])->assertSessionHas('status', '2 bookings cancelled.');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($related->fresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($keep->fresh()->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($unrelated->fresh()->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($related->statusHistory()->first()->reason)->toBe('Spam booking');
});

it('blocks and unblocks a booking IP, but never the admin\'s own IP', function (): void {
    $booking = upcomingBooking(['ip_address' => '198.51.100.7']);

    $this->actingAs($this->admin)->post("/control-panel/bookings/{$booking->id}/block-ip")->assertSessionHasNoErrors();
    expect(BlockedIp::isBlocked('198.51.100.7'))->toBeTrue();

    $this->delete("/control-panel/bookings/{$booking->id}/block-ip");
    expect(BlockedIp::isBlocked('198.51.100.7'))->toBeFalse();

    $own = upcomingBooking(['ip_address' => '127.0.0.1']); // the test request's own IP
    $this->post("/control-panel/bookings/{$own->id}/block-ip")->assertSessionHasErrors('ip');
    expect(BlockedIp::isBlocked('127.0.0.1'))->toBeFalse();
});

it('requires the bookings.manage permission to cancel or block', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('bookings.view');
    $booking = upcomingBooking(['ip_address' => '198.51.100.7']);

    $this->actingAs($viewer)->post("/control-panel/bookings/{$booking->id}/block-ip")->assertForbidden();
    $this->post("/control-panel/bookings/{$booking->id}/cancel-related", ['booking_ids' => [$booking->id], 'reason' => 'x'])->assertForbidden();

    expect(BlockedIp::query()->count())->toBe(0)
        ->and($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
});
