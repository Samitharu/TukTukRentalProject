<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Http\Requests\Admin\Auth\LoginRequest;
use Modules\Admin\Models\BlockedIp;
use Modules\Admin\Models\LoginAttempt;

final class LoginController extends Controller
{
    public function create(): View
    {
        return view('admin::admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $ip = (string) $request->ip();
        $email = (string) $request->string('email');

        if (BlockedIp::isBlocked($ip)) {
            abort(403, 'Access denied.');
        }

        $maxAttempts = (int) config('admin.login.max_attempts');
        $lockoutMinutes = (int) config('admin.login.lockout_minutes');

        if (LoginAttempt::recentFailedCountFor($email, $ip, $lockoutMinutes) >= $maxAttempts) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Too many failed attempts. Please try again in :minutes minutes.', ['minutes' => $lockoutMinutes])]);
        }

        $credentials = $request->only('email', 'password');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            // Spend the same ~one bcrypt hash an existing account costs, so
            // response time doesn't reveal which emails have accounts.
            Hash::make((string) $request->string('password'));
        }

        // Password is checked before is_active for the same reason: an
        // inactive account must not answer measurably faster than a wrong
        // password does.
        $passed = $user !== null && Auth::validate($credentials) && $user->is_active;

        if ($passed) {
            Auth::login($user);
        }

        LoginAttempt::query()->create([
            'email' => $email,
            'ip' => $ip,
            'successful' => $passed,
            'user_agent' => (string) $request->userAgent(),
            'created_at' => now(),
        ]);

        if (! $passed) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('These credentials do not match our records.')]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_login_at', now());
        $request->session()->put('admin_last_activity', now());
        $request->session()->forget('admin_2fa_passed');

        /** @var User $authenticated */
        $authenticated = Auth::user();
        $authenticated->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
