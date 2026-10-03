<?php

declare(strict_types=1);

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

it('forces a Super Admin without 2FA confirmed into the setup screen', function (): void {
    Role::findOrCreate('Super Admin');
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $response = $this->actingAs($user)->get('/control-panel');

    $response->assertRedirect('/control-panel/2fa/setup');
});

it('does not force 2FA on a role that is not in the enforced list', function (): void {
    Role::findOrCreate('Booking Agent');
    $user = User::factory()->create();
    $user->assignRole('Booking Agent');

    $response = $this->actingAs($user)->get('/control-panel');

    $response->assertOk();
});

it('lets a Super Admin confirm 2FA and then reach the dashboard', function (): void {
    Role::findOrCreate('Super Admin');
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user);

    $secret = (new Google2FA())->generateSecretKey();
    $this->withSession(['pending_2fa_secret' => $secret]);

    $code = (new Google2FA())->getCurrentOtp($secret);

    $confirm = $this->post('/control-panel/2fa/setup', ['code' => $code]);
    $confirm->assertRedirect('/control-panel/2fa/recovery-codes');

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeTrue();

    $dashboard = $this->get('/control-panel');
    $dashboard->assertOk();
});

it('challenges 2FA on a fresh session even after it was previously confirmed', function (): void {
    Role::findOrCreate('Super Admin');
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $secret = (new Google2FA())->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => [],
        'two_factor_confirmed_at' => now(),
    ])->save();

    // A new session (no admin_2fa_passed flag) must re-challenge, even though
    // the user already has 2FA fully enabled — the pass is per-session.
    $response = $this->actingAs($user)->get('/control-panel');

    $response->assertRedirect('/control-panel/2fa/challenge');
});
