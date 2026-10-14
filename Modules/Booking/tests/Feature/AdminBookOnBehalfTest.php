<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\Booking\Mail\BookingConfirmationMail;
use Modules\Booking\Mail\NewBookingPlacedMail;
use Modules\Booking\Models\Booking;
use Modules\Customer\Models\Customer;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Spatie\Permission\Models\Permission;

/**
 * Staff booking on a customer's behalf (walk-in / phone / WhatsApp): pick
 * an existing customer, choose add-ons, preview the price and availability
 * live, and choose whether the customer gets the confirmation email.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    config(['booking.admin_notification_emails' => ['owner@tuktuk.test']]);

    foreach (['bookings.view', 'bookings.manage', 'customers.view'] as $permission) {
        Permission::findOrCreate($permission);
    }
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['bookings.view', 'bookings.manage', 'customers.view']);
    $this->actingAs($this->admin);

    $this->vehicle = Vehicle::factory()->create();
    $this->package = Package::factory()->create(['min_days' => 1, 'max_days' => null]);
    $this->package->pricingTiers()->create(['min_days' => 1, 'max_days' => null, 'price' => 20]);
    $this->package->vehicles()->attach($this->vehicle->id);

    $this->helmet = Addon::factory()->create(['is_active' => true, 'max_quantity' => 3, 'price' => 5, 'pricing_unit' => Addon::UNIT_FLAT]);
    $this->package->addons()->attach($this->helmet->id, ['is_included' => false]);

    $this->customer = Customer::factory()->create([
        'full_name' => 'Returning Rita',
        'email' => 'rita@example.test',
        'phone' => '+49 151 2345678',
        'passport_number' => null,
    ]);
});

function behalfPayload(array $overrides = []): array
{
    return array_merge([
        'package_id' => (string) test()->package->id,
        'vehicle_id' => '',
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(12)->toDateString(), // 3 days
        'pickup_type' => 'office',
        'full_name' => 'Walk In Wendy',
        'email' => 'wendy@example.test',
    ], $overrides);
}

it('prefills the form from the customer page link', function (): void {
    $this->get("/control-panel/customers/{$this->customer->id}")
        ->assertSee('New booking for this customer')
        ->assertSee("/control-panel/bookings/create?customer={$this->customer->id}", false);

    $this->get("/control-panel/bookings/create?customer={$this->customer->id}")
        ->assertOk()
        ->assertSee('Returning Rita (rita@example.test)')
        ->assertSee('value="'.$this->customer->id.'"', false);
});

it('books for a picked existing customer without retyping or overwriting their details', function (): void {
    $this->post('/control-panel/bookings', behalfPayload([
        'customer_id' => (string) $this->customer->id,
        'full_name' => '',
        'email' => 'someone-else@example.test', // ignored: the picked customer's email wins
        'phone' => '+44 7000 000000',
        'passport_number' => 'X9876543',
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $booking = Booking::query()->sole();
    $customer = $this->customer->fresh();

    expect($booking->customer_id)->toBe($this->customer->id)
        ->and(Customer::query()->count())->toBe(1)
        ->and($customer->phone)->toBe('+49 151 2345678') // kept
        ->and($customer->passport_number)->toBe('X9876543'); // was missing, so filled in
});

it('still needs a name and email for a new customer', function (): void {
    $this->post('/control-panel/bookings', behalfPayload(['full_name' => '', 'email' => '']))
        ->assertSessionHasErrors(['full_name', 'email']);

    $this->post('/control-panel/bookings', behalfPayload(['nationality' => 'XX']))
        ->assertSessionHasErrors('nationality');

    expect(Booking::query()->count())->toBe(0);
});

it('prices the chosen add-on quantities and ignores add-ons the package does not offer', function (): void {
    $notOffered = Addon::factory()->create(['is_active' => true, 'max_quantity' => 5, 'price' => 100, 'pricing_unit' => Addon::UNIT_FLAT]);

    $this->post('/control-panel/bookings', behalfPayload([
        'addons' => [$this->helmet->id => '2', $notOffered->id => '1'],
    ]))->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();

    expect((float) $booking->total_amount)->toEqualWithDelta(70.0, 0.01) // 20 x 3 days + 2 x 5
        ->and($booking->addons()->pluck('quantity', 'addon_id')->all())->toBe([$this->helmet->id => 2]);
});

it('previews the same total and the free units before saving', function (): void {
    $this->postJson('/control-panel/bookings/quote', behalfPayload([
        'addons' => [$this->helmet->id => '1'],
        'full_name' => '', 'email' => '', // the customer part isn't needed for a quote
    ]))
        ->assertOk()
        ->assertJson(['total' => 65, 'units_total' => 1, 'units_free' => 1, 'coupon_applied' => false]);

    expect(Booking::query()->count())->toBe(0);

    $this->post('/control-panel/bookings', behalfPayload())->assertSessionHasNoErrors();

    $this->postJson('/control-panel/bookings/quote', behalfPayload())
        ->assertOk()
        ->assertJson(['units_free' => 0]);
});

it('reports form problems from the preview as validation errors', function (): void {
    $this->postJson('/control-panel/bookings/quote', behalfPayload(['end_date' => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_date');
});

it('finds existing customers by name, email or phone', function (): void {
    Customer::factory()->create(['full_name' => 'Other Person', 'email' => 'other@example.test', 'phone' => '0771111111']);

    $this->getJson('/control-panel/bookings/customer-lookup?q=rita')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.email', 'rita@example.test');

    $this->getJson('/control-panel/bookings/customer-lookup?q=2345678')->assertJsonPath('0.id', $this->customer->id);
    $this->getJson('/control-panel/bookings/customer-lookup?q=r')->assertExactJson([]);
});

it('does not let staff without customer access search customers', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo('bookings.manage');

    $this->actingAs($staff)->getJson('/control-panel/bookings/customer-lookup?q=rita')->assertForbidden();
});

it('emails the customer by default, and not when staff untick it', function (): void {
    Mail::fake();

    $this->post('/control-panel/bookings', behalfPayload(['notify_customer' => '1']))->assertSessionHasNoErrors();

    Mail::assertQueued(BookingConfirmationMail::class, fn ($mail) => $mail->hasTo('wendy@example.test'));

    $this->post('/control-panel/bookings', behalfPayload([
        'email' => 'quiet@example.test',
        'notify_customer' => '0',
        'start_date' => now()->addDays(20)->toDateString(),
        'end_date' => now()->addDays(21)->toDateString(),
    ]))->assertSessionHasNoErrors();

    Mail::assertNotQueued(BookingConfirmationMail::class, fn ($mail) => $mail->hasTo('quiet@example.test'));
    Mail::assertQueued(NewBookingPlacedMail::class, 2); // staff are still told about both
});
