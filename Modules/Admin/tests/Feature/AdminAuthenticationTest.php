<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Models\LoginAttempt;

it('redirects a guest to the login screen', function (): void {
    $this->get('/control-panel')->assertRedirect('/control-panel/login');
});

it('logs a user in with valid credentials and redirects to the dashboard', function (): void {
    $user = User::factory()->create([
        'email' => 'manager@example.test',
        'password' => Hash::make('a-very-strong-password'),
        'is_active' => true,
    ]);

    $response = $this->post('/control-panel/login', [
        'email' => 'manager@example.test',
        'password' => 'a-very-strong-password',
    ]);

    $response->assertRedirect('/control-panel');
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials and records a failed login attempt', function (): void {
    User::factory()->create([
        'email' => 'manager@example.test',
        'password' => Hash::make('a-very-strong-password'),
    ]);

    $response = $this->post('/control-panel/login', [
        'email' => 'manager@example.test',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(LoginAttempt::query()->where('successful', false)->count())->toBe(1);
});

it('locks out login after too many failed attempts from the same email', function (): void {
    User::factory()->create(['email' => 'manager@example.test', 'password' => Hash::make('correct-password')]);

    $maxAttempts = config('admin.login.max_attempts');

    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->post('/control-panel/login', ['email' => 'manager@example.test', 'password' => 'wrong']);
    }

    $response = $this->post('/control-panel/login', ['email' => 'manager@example.test', 'password' => 'correct-password']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects an inactive user even with correct credentials', function (): void {
    User::factory()->create([
        'email' => 'disabled@example.test',
        'password' => Hash::make('a-very-strong-password'),
        'is_active' => false,
    ]);

    $response = $this->post('/control-panel/login', [
        'email' => 'disabled@example.test',
        'password' => 'a-very-strong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs the user out and invalidates the session', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/control-panel/logout')->assertRedirect('/control-panel/login');

    $this->assertGuest();
});
