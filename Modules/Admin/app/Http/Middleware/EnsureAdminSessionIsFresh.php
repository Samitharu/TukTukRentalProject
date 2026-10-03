<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the configurable admin idle-timeout and absolute-session-lifetime
 * (brief §5 "System tools > Session management"). `admin_login_at` is set by
 * Modules\Admin\Http\Controllers\Admin\Auth\LoginController on successful
 * login, right after the session is regenerated.
 */
final class EnsureAdminSessionIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $idleMinutes = (int) config('admin.session.idle_minutes');
        $absoluteHours = (int) config('admin.session.absolute_hours');

        $loginAt = $request->session()->get('admin_login_at');
        $lastActivity = $request->session()->get('admin_last_activity');

        $now = now();

        $idleExpired = $lastActivity !== null && $now->diffInMinutes($lastActivity) > $idleMinutes;
        $absoluteExpired = $loginAt !== null && $now->diffInHours($loginAt) > $absoluteHours;

        if ($idleExpired || $absoluteExpired) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->with('status', $idleExpired
                    ? __('You were signed out due to inactivity.')
                    : __('Your session expired. Please sign in again.'));
        }

        $request->session()->put('admin_last_activity', $now);

        return $next($request);
    }
}
