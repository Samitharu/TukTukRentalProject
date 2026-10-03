<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\SiteSetting;
use Modules\Localization\Models\Locale;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Covers the admin-uploadable logo/hero image added after the "admin
 * should have the option to upload images" request — mirrors the
 * permission-gating style already used by FleetAuthorizationTest, plus
 * the upload/replace/remove lifecycle specific to SiteBrandingService.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Permission::findOrCreate('settings.manage');
    Role::findOrCreate('Branding Manager')->syncPermissions(['settings.manage']);
});

it('blocks a user without settings.manage from the branding page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/control-panel/branding')->assertForbidden();
});

it('lets an authorised user upload a logo and a hero image', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Branding Manager');

    $this->actingAs($admin)->post('/control-panel/branding', [
        'logo' => UploadedFile::fake()->image('logo.jpg', 400, 150),
    ])->assertRedirect('/control-panel/branding');

    $settings = SiteSetting::current();
    expect($settings->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->logo_path);

    $this->actingAs($admin)->post('/control-panel/branding', [
        'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 1000),
    ])->assertRedirect('/control-panel/branding');

    $settings = SiteSetting::current();
    expect($settings->hero_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->hero_image_path);
});

it('deletes the old file when the logo is replaced', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Branding Manager');

    $this->actingAs($admin)->post('/control-panel/branding', [
        'logo' => UploadedFile::fake()->image('first.jpg', 400, 150),
    ]);
    $firstPath = SiteSetting::current()->logo_path;

    $this->actingAs($admin)->post('/control-panel/branding', [
        'logo' => UploadedFile::fake()->image('second.jpg', 400, 150),
    ]);
    $secondPath = SiteSetting::current()->logo_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

it('removes the hero image and the underlying file', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Branding Manager');

    $this->actingAs($admin)->post('/control-panel/branding', [
        'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 1000),
    ]);
    $path = SiteSetting::current()->hero_image_path;

    $this->actingAs($admin)->delete('/control-panel/branding/hero-image')->assertRedirect('/control-panel/branding');

    expect(SiteSetting::current()->hero_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('shows the uploaded logo in the public header and no logo falls back to the icon mark', function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    $this->get('/en/')->assertSee('site-header__logo-icon', false);

    $admin = User::factory()->create();
    $admin->assignRole('Branding Manager');
    $this->actingAs($admin)->post('/control-panel/branding', [
        'logo' => UploadedFile::fake()->image('logo.jpg', 400, 150),
    ]);

    $response = $this->get('/en/');
    $response->assertSee('site-header__logo-img', false);
    $response->assertDontSee('site-header__logo-icon', false);
});
