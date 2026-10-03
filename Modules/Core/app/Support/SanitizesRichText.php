<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Mews\Purifier\Facades\Purifier;

/**
 * Brief §8: admin-authored rich text is rendered with `{!! !!}` (unescaped)
 * on public pages, so it must be whitelist-sanitized *before* it's ever
 * stored — sanitizing only at render time would still leave stored XSS
 * payloads sitting in the database for any other code path that might
 * render them unsanitized later. Call `purifyTranslatableHtml()` from a
 * `static::saving()` hook for every rich-text translatable field a model has.
 */
trait SanitizesRichText
{
    /**
     * @param array<string,string>|null $value
     * @return array<string,string>|null
     */
    protected function purifyTranslatableHtml(?array $value): ?array
    {
        if ($value === null) {
            return null;
        }

        foreach ($value as $locale => $html) {
            $value[$locale] = Purifier::clean((string) $html, 'cms_content');
        }

        return $value;
    }
}
