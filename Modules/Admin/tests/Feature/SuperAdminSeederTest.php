<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Database\Seeders\SuperAdminSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('bookings.manage');
    config(['admin.super_admin' => [
        'name' => 'Test Owner',
        'email' => 'Owner@Example.test',
        'password' => 'first-password',
    ]]);
});

it('creates a Super Admin holding every permission who can log in', function (): void {
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->where('email', 'owner@example.test')->firstOrFail();

    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->can('bookings.manage'))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->password)->not->toBe('first-password');

    $this->post('/control-panel/login', ['email' => 'owner@example.test', 'password' => 'first-password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('keeps a changed password when run again', function (): void {
    $this->seed(SuperAdminSeeder::class);
    User::query()->where('email', 'owner@example.test')->update(['password' => Hash::make('changed-password')]);

    $this->seed(SuperAdminSeeder::class);

    expect(User::query()->where('email', 'owner@example.test')->count())->toBe(1)
        ->and(Hash::check('changed-password', (string) User::query()->where('email', 'owner@example.test')->value('password')))->toBeTrue();
});

it('restores an account that was deactivated and removed', function (): void {
    $this->seed(SuperAdminSeeder::class);
    $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $user->update(['is_active' => false]);
    $user->delete();

    $this->seed(SuperAdminSeeder::class);

    expect($user->fresh()->trashed())->toBeFalse()
        ->and($user->fresh()->is_active)->toBeTrue()
        ->and($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('creates nobody when the address or password is blank', function (array $blank): void {
    config(['admin.super_admin' => [...config('admin.super_admin'), ...$blank]]);

    $this->seed(SuperAdminSeeder::class);

    expect(User::query()->count())->toBe(0);
})->with([
    'no address' => [['email' => '']],
    'no password' => [['password' => '']],
]);
