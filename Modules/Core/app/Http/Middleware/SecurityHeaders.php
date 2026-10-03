<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the OWASP-recommended security headers to every response, including
 * a nonce-based Content-Security-Policy. The nonce is generated once per
 * request and exposed via the `csp_nonce()` helper so Blade views can attach
 * it to any inline <script>/<style> tag they legitimately need.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            // Deliberately no 'unsafe-eval': public/assets/vendor/alpine.min.js
            // is the official @alpinejs/csp build (not regular Alpine), which
            // evaluates x-data/@click/x-show expressions with its own
            // restricted-grammar interpreter instead of `new Function(...)` —
            // regular Alpine's expression evaluator needs 'unsafe-eval' to
            // run AT ALL under any CSP, which would otherwise mean choosing
            // between a strict script-src and Alpine actually working.
            "script-src 'self' 'nonce-{$nonce}'",
            // CSP has no nonce/hash mechanism for inline style="" ATTRIBUTES
            // (only for <style> elements) — 'unsafe-inline' is the only way
            // to allow them, and per spec a nonce-source present in the SAME
            // directive makes browsers ignore 'unsafe-inline' entirely
            // (confirmed empirically, not just by reading the spec) — so
            // nonce and 'unsafe-inline' can't be combined here the way they
            // can in script-src. The views use inline style attributes
            // extensively for one-off layout tweaks, so this is a deliberate,
            // narrow trade-off in favour of 'unsafe-inline' alone.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'upgrade-insecure-requests',
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
