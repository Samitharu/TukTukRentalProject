<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Support\GoogleMapsLink;

/**
 * Only Google Maps links are accepted: the value is rendered as a link on
 * the public site, so an arbitrary URL here would be a phishing vector.
 */
final class GoogleMapsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! GoogleMapsLink::isGoogleMapsUrl($value)) {
            $fail(__('Paste a Google Maps link (from "Share → Copy link" in Google Maps, or the address bar).'));
        }
    }
}
