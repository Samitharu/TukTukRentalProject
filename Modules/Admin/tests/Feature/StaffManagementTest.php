<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(AdminDatabaseSeeder::class);

    // 2FA enforcement has its own tests (TwoFactorEnforcementTest); keep
    // these focused purely on who may manage whom.
    config(['admin.two_factor_enforced_roles' => []]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->owner = User::factory()->create();
    $this->owner->assignRole('Super Admin');
});

function staffPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Staff',
        'email' => 'staff@example.test',
        'password' => 'a-long-password-123',
        'password_confirmation' => 'a-long-password-123',
        'role' => 'Booking Agent',
    ], $overrides);
}

it('lets an Admin create a Booking Agent', function (): void {
    $this->actingAs($this->admin)
        ->post('/control-panel/users', staffPayload())
        ->assertRedirect('/control-panel/users');

    expect(User::query()->where('email', 'staff@example.test')->first()?->hasRole('Booking Agent'))->toBeTrue();
});

it('does not offer the Super Admin role to an Admin', function (): void {
    $this->actingAs($this->admin)
        ->get('/control-panel/users/create')
        ->assertOk()
        ->assertSee('value="Booking Agent"', false)
        ->assertDontSee('value="Super Admin"', false);
});

it('blocks an Admin from creating a Super Admin', function (): void {
    $this->actingAs($this->admin)
        ->post('/control-panel/users', staffPayload(['role' => 'Super Admin']))
        ->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'staff@example.test')->exists())->toBeFalse();
});

it('blocks an Admin from promoting themselves to Super Admin', function (): void {
    $this->actingAs($this->admin)
        ->put("/control-panel/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'Super Admin',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('role');

    expect($this->admin->fresh()->isSuperAdmin())->toBeFalse();
});

it('blocks an Admin from assigning a role with permissions they lack', function (): void {
    Role::findOrCreate('Language Admin')->syncPermissions(['locales.view', 'locales.manage']);

    $this->actingAs($this->admin)
        ->post('/control-panel/users', staffPayload(['role' => 'Language Admin']))
        ->assertSessionHasErrors('role');
});

it('keeps an Admin away from the Super Admin account', function (): void {
    $originalPassword = $this->owner->password;

    $this->actingAs($this->admin)
        ->get("/control-panel/users/{$this->owner->id}/edit")
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->put("/control-panel/users/{$this->owner->id}", [
            'name' => 'Hijacked',
            'email' => 'attacker@example.test',
            'password' => 'a-long-password-123',
            'password_confirmation' => 'a-long-password-123',
            'role' => 'Booking Agent',
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete("/control-panel/users/{$this->owner->id}")
        ->assertForbidden();

    $owner = $this->owner->fresh();
    expect($owner->email)->not->toBe('attacker@example.test')
        ->and($owner->password)->toBe($originalPassword)
        ->and($owner->isSuperAdmin())->toBeTrue();
});

it('lets a Super Admin assign the Super Admin role', function (): void {
    $this->actingAs($this->owner)
        ->post('/control-panel/users', staffPayload(['role' => 'Super Admin']))
        ->assertRedirect('/control-panel/users');

    expect(User::query()->where('email', 'staff@example.test')->first()?->isSuperAdmin())->toBeTrue();
});

it('blocks a Booking Agent from staff management', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('Booking Agent');

    $this->actingAs($agent)->get('/control-panel/users')->assertForbidden();
    $this->actingAs($agent)->post('/control-panel/users', staffPayload())->assertForbidden();
});

it('renames a legacy Manager role to Admin without losing its users', function (): void {
    Role::query()->where('name', 'Admin')->delete();
    $legacy = Role::findOrCreate('Manager');
    $user = User::factory()->create();
    $user->assignRole($legacy);

    $this->seed(AdminDatabaseSeeder::class);

    expect(Role::query()->where('name', 'Manager')->exists())->toBeFalse()
        ->and($user->fresh()->hasRole('Admin'))->toBeTrue()
        ->and($user->fresh()->can('packages.manage'))->toBeTrue();
});
