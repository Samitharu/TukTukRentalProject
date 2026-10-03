<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Localization\Models\Locale;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Permission::findOrCreate('locales.view');
    Permission::findOrCreate('locales.manage');
    Permission::findOrCreate('users.view');
    Permission::findOrCreate('users.manage');

    Role::findOrCreate('Content Editor')->syncPermissions(['locales.view']);
    Role::findOrCreate('Booking Agent')->syncPermissions([]);
    Role::findOrCreate('Super Admin')->syncPermissions(Permission::all());

    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
});

it('lets a Content Editor view the locales list', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Content Editor');

    $this->actingAs($user)->get('/control-panel/locales')->assertOk();
});

it('blocks a Content Editor from creating a locale', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Content Editor');

    $this->actingAs($user)->get('/control-panel/locales/create')->assertForbidden();

    $this->actingAs($user)->post('/control-panel/locales', [
        'code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'sort_order' => 5,
    ])->assertForbidden();
});

it('blocks a Booking Agent from the locales list entirely', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Booking Agent');

    $this->actingAs($user)->get('/control-panel/locales')->assertForbidden();
});

it('blocks a Content Editor from the user management screens', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Content Editor');

    $this->actingAs($user)->get('/control-panel/users')->assertForbidden();
});

// A role with full locale permissions but NOT in the 2FA-enforced list
// (that enforcement is exercised on its own in TwoFactorEnforcementTest) —
// keeps these cases focused purely on the authorization outcome.
it('lets a user with locales.manage handle locales end to end', function (): void {
    Role::findOrCreate('Locale Manager')->syncPermissions(['locales.view', 'locales.manage']);
    $user = User::factory()->create();
    $user->assignRole('Locale Manager');

    $this->actingAs($user)->post('/control-panel/locales', [
        'code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'sort_order' => 5,
    ])->assertRedirect('/control-panel/locales');

    expect(Locale::query()->where('code', 'it')->exists())->toBeTrue();
});

it('will not let the default locale be deleted', function (): void {
    Role::findOrCreate('Locale Manager')->syncPermissions(['locales.view', 'locales.manage']);
    $user = User::factory()->create();
    $user->assignRole('Locale Manager');
    $default = Locale::query()->where('is_default', true)->firstOrFail();

    $this->actingAs($user)
        ->delete("/control-panel/locales/{$default->code}")
        ->assertForbidden();

    expect(Locale::query()->find($default->id))->not->toBeNull();
});
