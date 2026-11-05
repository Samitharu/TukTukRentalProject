<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Only the address from Google Maps' "Share > Embed a map". It loads in a frame
 * on every public page, so nothing else may get through. Matched as one exact
 * pattern so tricks such as https://www.google.com@evil.example/ cannot pass.
 */
final class GoogleMapsEmbedUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('#^https://www\.google\.com/maps/embed\?[^\s"\'<>\\\\]+$#', $value) !== 1) {
            $fail(__('Paste the code from Google Maps: Share > Embed a map > Copy HTML.'));
        }
    }
}
