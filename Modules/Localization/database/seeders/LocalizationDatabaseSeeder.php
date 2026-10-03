<?php

declare(strict_types=1);

namespace Modules\Localization\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Localization\Models\Locale;

class LocalizationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // flag_icon is an ISO 3166-1 alpha-2 country code, not an emoji:
        // flag emoji (regional-indicator sequences) have no glyph in most
        // Windows fonts and render as two-letter codes in a box instead of
        // an actual flag — this code instead names a static SVG under
        // public/assets/icons/flags/{code}.svg, rendered via <img>, which
        // looks identical on every OS/browser.
        $locales = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'sort_order' => 1, 'flag_icon' => 'gb'],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => false, 'sort_order' => 2, 'flag_icon' => 'de'],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'is_default' => false, 'sort_order' => 3, 'flag_icon' => 'ru'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'is_default' => false, 'sort_order' => 4, 'flag_icon' => 'fr'],
        ];

        foreach ($locales as $locale) {
            Locale::query()->updateOrCreate(['code' => $locale['code']], [
                ...$locale,
                'is_active' => true,
            ]);
        }
    }
}
