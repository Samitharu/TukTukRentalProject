<?php

declare(strict_types=1);

namespace Modules\Localization\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Modules\Localization\Models\Locale;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Applied only to routes registered inside a locale-prefixed group (see
 * Modules\Localization\Services\LocaleRouteRegistrar). The {locale} route
 * parameter has already matched the URL prefix by the time this runs; here
 * we just validate it's still an active locale, apply it application-wide,
 * and refresh the remembered-choice cookie.
 *
 * Every locale-prefixed route shares ONE name across all locales (e.g.
 * "home", not "en.home") — {locale} is a route *parameter*, not a
 * per-locale name prefix. `URL::defaults()` fills that parameter in
 * automatically for every `route()` call made during this request, so
 * views just write `route('fleet.index')` and get the current locale's
 * URL without passing it explicitly every time.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = (string) $request->route('locale');

        $active = Locale::activeCached();

        if (! $active->contains('code', $code)) {
            throw new NotFoundHttpException();
        }

        app()->setLocale($code);
        Carbon::setLocale($code);
        URL::defaults(['locale' => $code]);

        $response = $next($request);

        $response->headers->setCookie(
            cookie(name: 'locale', value: $code, minutes: 60 * 24 * 365, httpOnly: true, sameSite: 'lax'),
        );

        return $response;
    }
}
