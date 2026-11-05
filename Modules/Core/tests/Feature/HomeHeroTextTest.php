<?php

declare(strict_types=1);

use Modules\Localization\Models\Locale;

/**
 * The homepage headline covers every service, with one phrase highlighted
 * in each language.
 */
it('highlights the chosen phrase in the headline', function (string $code, string $highlighted): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

    if ($code !== 'en') {
        Locale::query()->create(['code' => $code, 'name' => $code, 'native_name' => $code, 'is_default' => false, 'is_active' => true, 'sort_order' => 2]);
    }

    $this->get("/{$code}")
        ->assertOk()
        ->assertSee('<span class="hero__highlight">'.$highlighted.'<svg', false);
})->with([
    ['en', 'Sri Lanka'],
    ['de', 'Sri-Lanka'],
    ['fr', 'Sri Lanka'],
    ['ru', 'Шри-Ланке'],
]);
