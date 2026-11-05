<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Core\Models\SiteSetting;
use Modules\Localization\Models\Locale;
use Spatie\Permission\Models\Permission;

const FOOTER_MAP_URL = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3959.5!2d79.84!3d7.2!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1';

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    Permission::findOrCreate('settings.manage');
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('settings.manage');
});

it('shows no map until one is saved', function (): void {
    $this->get('/en')->assertOk()->assertDontSee('site-footer__map', false);
});

it('keeps only the address from the code Google gives you, and shows the map', function (): void {
    $code = '<iframe src="'.FOOTER_MAP_URL.'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';

    $this->actingAs($this->admin)->post('/control-panel/branding', ['footer_map_url' => $code])->assertSessionHasNoErrors();

    expect(SiteSetting::current()->footer_map_url)->toBe(FOOTER_MAP_URL);

    $this->get('/en')->assertOk()
        ->assertSee('site-footer__map', false)
        ->assertSee('src="'.FOOTER_MAP_URL.'"', false)
        ->assertDontSee('no-referrer-when-downgrade', false);
});

it('accepts the address on its own, and blank removes the map', function (): void {
    $this->actingAs($this->admin)->post('/control-panel/branding', ['footer_map_url' => FOOTER_MAP_URL])->assertSessionHasNoErrors();
    expect(SiteSetting::current()->footer_map_url)->toBe(FOOTER_MAP_URL);

    $this->actingAs($this->admin)->post('/control-panel/branding', ['footer_map_url' => ''])->assertSessionHasNoErrors();
    expect(SiteSetting::current()->footer_map_url)->toBeNull();

    $this->get('/en')->assertDontSee('site-footer__map', false);
});

it('refuses anything but a Google Maps embed', function (string $bad): void {
    $this->actingAs($this->admin)->post('/control-panel/branding', ['footer_map_url' => $bad])->assertSessionHasErrors('footer_map_url');

    expect(SiteSetting::current()->footer_map_url)->toBeNull();
})->with([
    'another site' => ['https://evil.example/maps/embed?pb=1'],
    'lookalike host' => ['https://www.google.com.evil.example/maps/embed?pb=1'],
    'hidden host' => ['https://www.google.com@evil.example/maps/embed?pb=1'],
    'not https' => ['http://www.google.com/maps/embed?pb=1'],
    'share link, not embed' => ['https://maps.app.goo.gl/AbCdEf123'],
    'script' => ['javascript:alert(1)'],
    'frame from another site' => ['<iframe src="https://evil.example/maps/embed?pb=1"></iframe>'],
    'breaking out of the attribute' => ['https://www.google.com/maps/embed?pb=1"><script>alert(1)</script>'],
]);

it('lets only Google frames through the security policy', function (): void {
    expect((string) $this->get('/en')->headers->get('Content-Security-Policy'))->toContain('frame-src https://www.google.com;');
});

it('blocks staff without the permission from setting a map', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/control-panel/branding', ['footer_map_url' => FOOTER_MAP_URL])
        ->assertForbidden();

    expect(SiteSetting::current()->footer_map_url)->toBeNull();
});
