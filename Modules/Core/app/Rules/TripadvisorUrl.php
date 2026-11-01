<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Only a link to a Tripadvisor site (tripadvisor.com, tripadvisor.co.uk,
 * tripadvisor.de…). It is shown on every public page, so an arbitrary URL
 * would be a phishing vector — same reasoning as GoogleMapsUrl.
 */
final class TripadvisorUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;
        $host = is_array($parts) ? mb_strtolower((string) ($parts['host'] ?? '')) : '';

        if (($parts['scheme'] ?? '') !== 'https' || preg_match('/^(www\.)?tripadvisor\.[a-z]{2,3}(\.[a-z]{2})?$/', $host) !== 1) {
            $fail(__('Paste the link to your Tripadvisor page (https://www.tripadvisor.com/...).'));
        }
    }
}
