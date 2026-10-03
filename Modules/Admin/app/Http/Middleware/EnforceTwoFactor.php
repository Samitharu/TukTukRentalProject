<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandatory TOTP 2FA for roles listed in config('admin.two_factor_enforced_roles')
 * (Super Admin by default — brief §5). Users without 2FA enabled at all are
 * forced into setup; users with 2FA enabled must pass the per-session
 * challenge before reaching any other admin route.
 */
final class EnforceTwoFactor
{
    private const array EXEMPT_ROUTES = [
        'admin.logout',
        'admin.2fa.setup',
        'admin.2fa.confirm',
        'admin.2fa.challenge',
        'admin.2fa.verify',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($user->hasTwoFactorEnabled()) {
            if (! $request->session()->get('admin_2fa_passed', false)) {
                return redirect()->route('admin.2fa.challenge');
            }

            return $next($request);
        }

        if ($user->requiresTwoFactor()) {
            return redirect()->route('admin.2fa.setup');
        }

        return $next($request);
    }
}
