<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Admin\Models\BlockedIp;
use Symfony\Component\HttpFoundation\Response;

final class RestrictAdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();

        if (BlockedIp::isBlocked($ip)) {
            abort(403, 'Access denied.');
        }

        $config = config('admin.ip_allowlist');

        if (($config['enabled'] ?? false) === true) {
            $allowed = $config['ips'] ?? [];

            if ($allowed !== [] && ! in_array($ip, $allowed, true)) {
                abort(403, 'Access denied.');
            }
        }

        return $next($request);
    }
}
