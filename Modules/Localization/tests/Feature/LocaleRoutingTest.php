<?php

declare(strict_types=1);

use Modules\Localization\Models\Locale;

function seedTestLocales(): void
{
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    Locale::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => false, 'is_active' => true, 'sort_order' => 2]);
    Locale::query()->create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'is_default' => false, 'is_active' => false, 'sort_order' => 3]);
}

it('redirects the root URL to the default locale when there is no signal', function (): void {
    seedTestLocales();

    $response = $this->get('/');

    $response->assertRedirect('/en');
});

it('redirects to the best-matching active locale from Accept-Language', function (): void {
    seedTestLocales();

    $response = $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8'])->get('/');

    $response->assertRedirect('/de');
});

it('never redirects a crawler based on Accept-Language, always the default', function (): void {
    seedTestLocales();

    $response = $this
        ->withHeaders([
            'Accept-Language' => 'de-DE,de;q=0.9',
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ])
        ->get('/');

    $response->assertRedirect('/en');
});

it('does not offer an inactive locale as a route prefix', function (): void {
    seedTestLocales();

    $response = $this->get('/fr');

    $response->assertNotFound();
});

it('remembers a locale choice via cookie on the next visit', function (): void {
    seedTestLocales();

    $response = $this->withCookie('locale', 'de')->get('/');

    $response->assertRedirect('/de');
});

it('serves the homepage in the requested active locale', function (): void {
    seedTestLocales();

    $response = $this->get('/de');

    $response->assertOk();
    $response->assertSee('lang="de"', false);
    // The real homepage replaced the Phase 2 "coming soon" placeholder in
    // Phase 5 — assert on its German hero copy instead.
    $response->assertSee('Entdecken Sie Sri Lanka auf Ihre Weise', false);
});
