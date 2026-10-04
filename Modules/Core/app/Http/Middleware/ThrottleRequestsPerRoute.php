<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Drop-in replacement for the `throttle` alias that gives every route its
 * own counter.
 *
 * Laravel's plain `throttle:5,1` keys its counter by client IP only (or
 * user id) — not by route — so every throttled route shared ONE counter
 * per visitor: four booking-step submits plus "Confirm" used up the review
 * form's limit of 5, and trying a few coupon codes on the review step
 * could get "Confirm" itself rejected with 429. Appending the route name
 * keeps each limit independent, as the route files intend.
 *
 * The name — not the URL — is used, so /en/... and /de/... share one
 * counter: switching language is not a way around a limit. Named limiters
 * (`throttle:api`) define their own keys and are unaffected.
 */
final class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $routeKey = $route?->getName() ?? $route?->uri() ?? '';

        return sha1(parent::resolveRequestSignature($request).'|'.$routeKey);
    }
}
