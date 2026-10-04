<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Models\VehicleReservationSlot;
use Modules\Booking\Models\Booking;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Spatie\Permission\Models\Permission;

/**
 * Booking a cabana/room through the public wizard: check-in/check-out
 * dates priced per night, no pickup or driving-licence questions, and the
 * check-out day left free for the next guest.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    BusinessLocation::factory()->create(['is_active' => true]);
    $this->tukTuk = Vehicle::factory()->create();
    $this->cabana = Vehicle::factory()->stay()->create(['name' => ['en' => 'Sunset Cabana']]);

    // "Surf & Stay": 25 per night, surf lessons bundled in as an add-on.
    $this->stayPackage = Package::factory()->create(['kind' => VehicleCategory::KIND_STAY, 'name' => ['en' => 'Surf & Stay'], 'min_days' => 1, 'max_days' => null, 'pricing_model' => Package::MODEL_PER_DAY]);
    $this->stayPackage->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 25]);
    $this->surfLesson = Addon::factory()->create(['is_active' => true, 'max_quantity' => 5, 'price' => 15, 'pricing_unit' => Addon::UNIT_FLAT]);
    $this->stayPackage->addons()->attach($this->surfLesson->id, ['is_included' => false]);

    // Deliberately unrestricted: "any active unit" must still mean tuk tuks only.
    $this->tukTukPackage = Package::factory()->create(['name' => ['en' => 'Island Explorer'], 'min_days' => 1, 'max_days' => null]);
    $this->tukTukPackage->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
});

function guestDetailsPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Kai',
        'last_name' => 'Surfer',
        'email' => 'kai.surfer@example.test',
        'phone' => '+61 400 000 000',
        'nationality' => 'AU',
        'passport_number' => 'PA1234567',
        'marketing_opt_in' => '0',
    ], $overrides);
}

function bookStay(string $checkIn, string $checkOut, string $email = 'kai.surfer@example.test'): void
{
    test()->get('/en/booking/start?package='.test()->stayPackage->id)->assertOk()->assertSee('Check-out');

    test()->post('/en/booking/start', ['start_date' => $checkIn, 'end_date' => $checkOut])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/en/booking/package');
    test()->post('/en/booking/package', ['package_id' => test()->stayPackage->id])->assertRedirect('/en/booking/addons');
    test()->post('/en/booking/addons', ['addons' => [test()->surfLesson->id => 2]])->assertRedirect('/en/booking/details');
    // No licence / permit fields at all for a stay.
    test()->post('/en/booking/details', guestDetailsPayload(['email' => $email]))->assertRedirect('/en/booking/review');
    test()->get('/en/booking/review')->assertOk()->assertSee('3 nights');
    test()->post('/en/booking/confirm', ['terms_accepted' => '1'])->assertRedirect();
}

it('books a cabana by the night and leaves the check-out day free', function (): void {
    bookStay('2027-01-10', '2027-01-13');

    $booking = Booking::query()->with('vehicle.category')->firstOrFail();

    expect($booking->vehicle_id)->toBe($this->cabana->id)
        ->and($booking->isStay())->toBeTrue()
        ->and($booking->start_at->toDateString())->toBe('2027-01-10')
        ->and($booking->end_at->toDateString())->toBe('2027-01-12') // last night
        ->and($booking->checkOutDate()->toDateString())->toBe('2027-01-13')
        ->and($booking->has_international_permit)->toBeFalse()
        ->and((float) $booking->total_amount)->toEqualWithDelta(105.0, 0.01); // 3 nights × 25 + 2 surf lessons × 15

    expect(VehicleReservationSlot::query()->where('vehicle_id', $this->cabana->id)->orderBy('slot_date')->pluck('slot_date')->map(fn ($d) => substr((string) $d, 0, 10))->all())
        ->toBe(['2027-01-10', '2027-01-11', '2027-01-12']);

    $this->get('/en/booking/confirmation/'.$booking->reference)
        ->assertOk()
        ->assertSee('13 Jan 2027')
        ->assertSee('Get directions');

    $this->get('/en/booking/confirmation/'.$booking->reference.'/receipt')->assertOk();
});

it('lets the next guest check in on the previous guest\'s check-out day', function (): void {
    bookStay('2027-01-10', '2027-01-13');
    bookStay('2027-01-13', '2027-01-16', 'second.guest@example.test');

    expect(Booking::query()->where('vehicle_id', $this->cabana->id)->count())->toBe(2);
});

it('only offers stay packages in a stay booking, and tuk tuk packages in a rental', function (): void {
    $this->get('/en/booking/start?kind=stay');
    $this->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-04']);

    $this->get('/en/booking/package')->assertOk()->assertSee('Surf & Stay')->assertDontSee('Island Explorer');
    $this->post('/en/booking/package', ['package_id' => $this->tukTukPackage->id])->assertNotFound();

    $this->get('/en/booking/start?kind=vehicle');
    $this->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-04', 'pickup_type' => 'office', 'business_location_id' => BusinessLocation::query()->value('id')]);

    $this->get('/en/booking/package')->assertOk()->assertSee('Island Explorer')->assertDontSee('Surf & Stay');
});

it('requires check-out to be after check-in', function (): void {
    $this->get('/en/booking/start?kind=stay');

    $this->post('/en/booking/start', ['start_date' => '2027-02-01', 'end_date' => '2027-02-01'])
        ->assertSessionHasErrors('end_date');
});

it('never auto-assigns a cabana to an unrestricted tuk tuk package', function (): void {
    expect($this->tukTukPackage->eligibleVehicleIds())->toBe([$this->tukTuk->id])
        ->and($this->stayPackage->eligibleVehicleIds())->toBe([$this->cabana->id]);
});

it('takes a check-out date for an admin manual stay booking', function (): void {
    Permission::findOrCreate('bookings.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo('bookings.manage');

    $this->actingAs($admin)->post('/control-panel/bookings', [
        'package_id' => (string) $this->stayPackage->id,
        'start_date' => '2027-03-01',
        'end_date' => '2027-03-03', // check-out → 2 nights
        'pickup_type' => 'office',
        'full_name' => 'Walk-in Guest',
        'email' => 'walkin@example.test',
    ])->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect($booking->vehicle_id)->toBe($this->cabana->id)
        ->and($booking->end_at->toDateString())->toBe('2027-03-02')
        ->and((float) $booking->total_amount)->toEqualWithDelta(50.0, 0.01);

    // A tuk tuk can't be forced onto a stay package.
    $this->actingAs($admin)->post('/control-panel/bookings', [
        'package_id' => (string) $this->stayPackage->id,
        'vehicle_id' => (string) $this->tukTuk->id,
        'start_date' => '2027-04-01',
        'end_date' => '2027-04-03',
        'pickup_type' => 'office',
        'full_name' => 'Walk-in Guest',
        'email' => 'walkin@example.test',
    ])->assertSessionHasErrors('vehicle_id');
});
