<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Localization\Models\Locale;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cabanas and rooms ("stays") as bookable units: admin entry with a Google
 * Maps location, and their own public pages separate from the tuk tuks.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    Permission::findOrCreate('fleet.view');
    Permission::findOrCreate('fleet.manage');
    Role::findOrCreate('Fleet Manager')->syncPermissions(['fleet.view', 'fleet.manage']);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('Fleet Manager');

    $this->stayCategory = VehicleCategory::factory()->stay()->create();
    $this->tukTukCategory = VehicleCategory::factory()->create();
});

function stayPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Sunset Cabana'],
        'category_id' => (string) test()->stayCategory->id,
        'seats' => '2',
        'features' => ['wifi', 'sea_view', 'helmet_included'], // a tuk tuk feature ticked by mistake
        'google_maps_url' => 'https://www.google.com/maps/place/Sunset+Cabana/@6.84,81.83,17z/data=!3d6.8412!4d81.8355',
        'address' => 'Whiskey Point, Arugam Bay',
        'status' => 'active',
    ], $overrides);
}

it('adds a cabana without a plate number and reads its pin from the Google Maps link', function (): void {
    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', stayPayload([
            // Vehicle-only fields a hidden form section might still send.
            'plate_no' => 'SHOULD-BE-IGNORED',
            'transmission' => 'manual',
        ]))
        ->assertRedirect('/control-panel/fleet/vehicles')
        ->assertSessionHasNoErrors();

    $cabana = Vehicle::query()->with('category')->firstOrFail();

    expect($cabana->isStay())->toBeTrue()
        ->and($cabana->plate_no)->toBeNull()
        ->and($cabana->transmission)->toBeNull()
        ->and((float) $cabana->lat)->toBe(6.8412)
        ->and((float) $cabana->lng)->toBe(81.8355)
        ->and($cabana->features)->toBe(['wifi', 'sea_view'])
        ->and($cabana->directionsUrl())->toBe('https://www.google.com/maps/dir/?api=1&destination=6.8412000,81.8355000');
});

it('expands a maps.app.goo.gl share link on save', function (): void {
    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/X/@7.1,81.1,17z/data=!3d7.1234!4d81.5678']),
    ]);

    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', stayPayload(['google_maps_url' => 'https://maps.app.goo.gl/AbCdEf123']))
        ->assertSessionHasNoErrors();

    $cabana = Vehicle::query()->firstOrFail();

    expect($cabana->google_maps_url)->toBe('https://maps.app.goo.gl/AbCdEf123')
        ->and((float) $cabana->lat)->toBe(7.1234)
        ->and((float) $cabana->lng)->toBe(81.5678);
});

it('still saves a link it cannot read, and tells staff to drop the pin', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', stayPayload(['google_maps_url' => 'https://maps.app.goo.gl/Broken']))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'drop the pin'));

    expect(Vehicle::query()->firstOrFail()->lat)->toBeNull();
});

it('requires a location for a stay but not for a tuk tuk', function (): void {
    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', stayPayload(['google_maps_url' => null]))
        ->assertSessionHasErrors('google_maps_url');

    // A dropped pin alone is enough.
    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', stayPayload(['google_maps_url' => null, 'lat' => '6.84', 'lng' => '81.83']))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', [
            'name' => ['en' => 'Tuk 1'],
            'category_id' => (string) $this->tukTukCategory->id,
            'plate_no' => 'WP-1234',
            'seats' => '3',
            'transmission' => 'manual',
            'fuel_type' => 'petrol',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();
});

it('still requires a plate number for a tuk tuk', function (): void {
    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', [
            'name' => ['en' => 'Tuk 1'],
            'category_id' => (string) $this->tukTukCategory->id,
            'seats' => '3',
            'transmission' => 'manual',
            'fuel_type' => 'petrol',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('plate_no');
});

it('rejects a link that is not a Google Maps link', function (): void {
    $this->actingAs($this->manager)
        ->post('/control-panel/fleet/vehicles', stayPayload(['google_maps_url' => 'https://evil.example/maps/@6.8,81.8']))
        ->assertSessionHasErrors('google_maps_url');
});

it('re-reads the pin when the link changes but the old pin was submitted unchanged', function (): void {
    $cabana = Vehicle::factory()->stay()->create(['category_id' => $this->stayCategory->id]);

    $this->actingAs($this->manager)
        ->put("/control-panel/fleet/vehicles/{$cabana->id}", stayPayload([
            'google_maps_url' => 'https://www.google.com/maps/@7.5,80.5,14z',
            'lat' => (string) $cabana->lat,
            'lng' => (string) $cabana->lng,
        ]))
        ->assertSessionHasNoErrors();

    expect((float) $cabana->fresh()->lat)->toBe(7.5)
        ->and((float) $cabana->fresh()->lng)->toBe(80.5);
});

it('will not change the type of a category that already has units', function (): void {
    Vehicle::factory()->create(['category_id' => $this->tukTukCategory->id]);

    $this->actingAs($this->manager)
        ->put("/control-panel/fleet/categories/{$this->tukTukCategory->id}", [
            'name' => ['en' => 'Standard'],
            'kind' => 'stay',
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('kind');
});

it('lists stays and tuk tuks on their own public pages', function (): void {
    $cabana = Vehicle::factory()->stay()->create(['category_id' => $this->stayCategory->id, 'name' => ['en' => 'Sunset Cabana']]);
    Vehicle::factory()->create(['category_id' => $this->tukTukCategory->id, 'name' => ['en' => 'Red Tuk Tuk']]);

    $this->get('/en/stays')->assertOk()->assertSee('Sunset Cabana')->assertDontSee('Red Tuk Tuk');
    $this->get('/en/tuk-tuks')->assertOk()->assertSee('Red Tuk Tuk')->assertDontSee('Sunset Cabana');

    $slug = $cabana->slugFor('en');

    $this->get("/en/stays/{$slug}")
        ->assertOk()
        ->assertSee('Get directions')
        ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=6.8406000,81.8368000', false)
        ->assertSee('data-location-map', false);

    $this->get("/en/tuk-tuks/{$slug}")->assertRedirect("/en/stays/{$slug}");
});
