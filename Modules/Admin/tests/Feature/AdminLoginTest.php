<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

it('takes a Super Admin straight to the dashboard with just email and password', function (): void {
    Role::findOrCreate('Super Admin');
    $user = User::factory()->create(['email' => 'owner@example.test', 'password' => 'correct horse battery staple', 'is_active' => true]);
    $user->assignRole('Super Admin');

    $this->post('/control-panel/login', ['email' => 'owner@example.test', 'password' => 'correct horse battery staple'])
        ->assertRedirect('/control-panel');

    $this->assertAuthenticatedAs($user);
    $this->get('/control-panel')->assertOk();
});

it('has no authenticator screens any more', function (): void {
    $user = User::factory()->create();

    foreach (['setup', 'challenge', 'recovery-codes'] as $screen) {
        $this->actingAs($user)->get("/control-panel/2fa/{$screen}")->assertNotFound();
    }
});

it('still refuses a wrong password', function (): void {
    User::factory()->create(['email' => 'owner@example.test', 'password' => 'correct horse battery staple']);

    $this->post('/control-panel/login', ['email' => 'owner@example.test', 'password' => 'wrong password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
