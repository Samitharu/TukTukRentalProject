<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a route group out of search engines: the booking wizard (its
 * confirmation/status/receipt URLs carry a customer's booking reference)
 * and the control panel.
 *
 * The X-Robots-Tag header covers every response type (the PDF receipt
 * too); the request attribute makes the public layout print the matching
 * <meta name="robots"> instead of its default "index, follow". These pages
 * are deliberately NOT disallowed in robots.txt — a crawler that can't
 * fetch a page never sees its noindex and may still list the bare URL.
 */
final class NoIndex
{
    public const string ATTRIBUTE = 'seo_noindex';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, true);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
