<?php

declare(strict_types=1);

namespace Modules\Localization\Support;

/**
 * A small, deliberately static list of major search-engine crawler
 * User-Agent substrings. This is a low-maintenance, rarely-changing list
 * (see docs/01-architecture.md §4), used only to decide whether Accept-Language
 * based locale *guessing* applies — bots always get the default locale instead,
 * never a guessed-language redirect, per the brief's "never auto-redirect
 * search engine bots" requirement.
 */
final class CrawlerDetector
{
    private const array SIGNATURES = [
        'googlebot',
        'bingbot',
        'yandexbot',
        'duckduckbot',
        'baiduspider',
        'slurp',
        'facebookexternalhit',
        'twitterbot',
        'applebot',
        'petalbot',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
    ];

    public static function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        $userAgent = mb_strtolower($userAgent);

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($userAgent, $signature)) {
                return true;
            }
        }

        return false;
    }
}
