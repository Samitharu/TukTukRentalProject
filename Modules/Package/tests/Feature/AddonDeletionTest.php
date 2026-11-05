<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingAddon;
use Modules\Customer\Models\Customer;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Addon;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    $this->seed(AdminDatabaseSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->addon = Addon::factory()->create(['name' => ['en' => 'Helmet'], 'is_active' => true]);
});

it('removes an add-on that no booking uses', function (): void {
    $this->actingAs($this->admin)
        ->delete(route('admin.addons.destroy', $this->addon))
        ->assertRedirect(route('admin.addons.index'))
        ->assertSessionHas('status');

    expect(Addon::query()->find($this->addon->id))->toBeNull();
});

it('explains instead of crashing when bookings use the add-on, and keeps it', function (): void {
    $booking = Booking::factory()->create(['customer_id' => Customer::factory()]);
    BookingAddon::query()->create(['booking_id' => $booking->id, 'addon_id' => $this->addon->id, 'quantity' => 1, 'unit_price' => 5]);

    $this->actingAs($this->admin)
        ->from(route('admin.addons.index'))
        ->delete(route('admin.addons.destroy', $this->addon))
        ->assertRedirect(route('admin.addons.index'))
        ->assertSessionHasErrors('addon');

    expect(Addon::query()->find($this->addon->id))->not->toBeNull()
        ->and($booking->addons()->count())->toBe(1);

    $this->actingAs($this->admin)->get(route('admin.addons.index'))
        ->assertOk()
        ->assertSee('Helmet cannot be removed');
});
