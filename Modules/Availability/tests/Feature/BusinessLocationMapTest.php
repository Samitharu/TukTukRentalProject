<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Availability\Models\BusinessLocation;
use Modules\Localization\Models\Locale;
use Spatie\Permission\Models\Permission;

/**
 * Pickup locations take a Google Maps link the same way tuk tuks and stays
 * do: the pin is read from the link (short links expanded on save), and
 * only Google Maps links are accepted since customers are sent to them.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    Permission::findOrCreate('availability.view');
    Permission::findOrCreate('availability.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo(['availability.view', 'availability.manage']);
    $this->actingAs($admin);
});

function locationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Negombo Office'],
        'google_maps_url' => 'https://www.google.com/maps/place/Negombo/@7.2,79.8,17z/data=!3d7.2083!4d79.8358',
        'address' => 'Lewis Place, Negombo',
        'is_pickup_point' => '1',
        'is_active' => '1',
    ], $overrides);
}

it('saves a location with its Google Maps link and reads the pin from it', function (): void {
    $this->get('/control-panel/locations/create')->assertOk()->assertSee('data-location-picker', false);

    $this->post('/control-panel/locations', locationPayload())->assertRedirect()->assertSessionHasNoErrors();

    $location = BusinessLocation::query()->sole();
    expect($location->google_maps_url)->toContain('google.com/maps/place/Negombo')
        ->and((float) $location->lat)->toBe(7.2083)
        ->and((float) $location->lng)->toBe(79.8358)
        ->and($location->mapUrl())->toBe($location->google_maps_url);

    $this->get('/control-panel/locations')->assertOk()->assertSee($location->google_maps_url, false);
});

it('expands a maps.app.goo.gl share link on save', function (): void {
    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/X/@7.1,79.8,17z/data=!3d7.1234!4d79.8567']),
    ]);

    $this->post('/control-panel/locations', locationPayload(['google_maps_url' => 'https://maps.app.goo.gl/AbCdEf123']))
        ->assertSessionHasNoErrors();

    expect((float) BusinessLocation::query()->sole()->lat)->toBe(7.1234);
});

it('updates the pin when the link changes but the form still sent the old pin', function (): void {
    $location = BusinessLocation::factory()->create(['lat' => 7.0, 'lng' => 79.0, 'google_maps_url' => 'https://maps.app.goo.gl/Old']);

    $this->put('/control-panel/locations/'.$location->id, locationPayload(['lat' => '7.0', 'lng' => '79.0']))
        ->assertSessionHasNoErrors();

    expect((float) $location->fresh()->lat)->toBe(7.2083);
});

it('rejects a link that is not Google Maps', function (): void {
    $this->post('/control-panel/locations', locationPayload(['google_maps_url' => 'https://evil.example/maps/@7.2,79.8']))
        ->assertSessionHasErrors('google_maps_url');

    expect(BusinessLocation::query()->count())->toBe(0);
});

it('still saves a location with no map at all', function (): void {
    $this->post('/control-panel/locations', locationPayload(['google_maps_url' => null]))->assertSessionHasNoErrors();

    expect(BusinessLocation::query()->sole()->mapUrl())->toBeNull();
});
