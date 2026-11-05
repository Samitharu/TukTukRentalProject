<?php

declare(strict_types=1);

/**
 * A key missing from a language silently falls back to English, which gave
 * German, French and Russian customers half-English booking receipts.
 */
it('has every customer text in every language', function (string $locale): void {
    $english = require module_path('Core', 'lang/en/front.php');
    $translated = require module_path('Core', "lang/{$locale}/front.php");

    expect(array_keys(array_diff_key($english, $translated)))->toBe([]);
})->with(['de', 'fr', 'ru']);
