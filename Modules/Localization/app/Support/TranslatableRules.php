<?php

declare(strict_types=1);

namespace Modules\Localization\Support;

use Modules\Localization\Models\Locale;

/**
 * Generates the per-locale validation rule set for a `spatie/laravel-translatable`
 * JSON field, so every module's Form Requests don't hand-write the same
 * locale-by-locale block — and so the set of locales validated against is
 * always the *current* active list (Locale::activeCached()), not a
 * hardcoded array. This is what lets adding a 5th language from the admin
 * apply to every existing translatable form without a code change.
 *
 * The current default locale is required; the others are optional — admin
 * content doesn't have to be fully translated before it can be saved
 * (missing translations are tracked, not blocked — see MissingTranslationLog).
 */
final class TranslatableRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function forField(string $field, string $type = 'string', int $max = 255, bool $requireDefault = true): array
    {
        $rules = [$field => ['nullable', 'array']];
        $defaultCode = Locale::defaultCode();

        foreach (Locale::activeCached()->pluck('code') as $code) {
            $rules["{$field}.{$code}"] = $requireDefault && $code === $defaultCode
                ? ['required', $type, "max:{$max}"]
                : ['nullable', $type, "max:{$max}"];
        }

        return $rules;
    }
}
