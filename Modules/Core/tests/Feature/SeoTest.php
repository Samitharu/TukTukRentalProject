<?php

declare(strict_types=1);

use Modules\CMS\Models\Faq;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    Locale::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => false, 'is_active' => true, 'sort_order' => 2]);
});

/** The site root as the test requests see it (APP_URL). */
function seoBase(): string
{
    return rtrim((string) config('app.url'), '/');
}

/** @return array<int, array<string, mixed>> */
function jsonLdBlocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

it('gives the home page canonical, hreflang, social and business tags', function (): void {
    $html = $this->get('/en')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="robots" content="index, follow, max-image-preview:large">')
        ->toContain('<link rel="canonical" href="'.seoBase().'/en">')
        ->toContain('<link rel="alternate" hreflang="en" href="'.seoBase().'/en">')
        ->toContain('<link rel="alternate" hreflang="de" href="'.seoBase().'/de">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="'.seoBase().'/en">')
        ->toContain('<meta property="og:title"')
        ->toContain('<meta property="og:image" content="'.seoBase().'/')
        ->toContain('<meta property="og:locale:alternate" content="de_DE">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->and(substr_count($html, '<meta name="description"'))->toBe(1);

    $types = array_column(jsonLdBlocks($html), '@type');
    expect($types)->toContain('WebSite')->toContain('AutoRental');
});

it('targets tuk tuk rental in Negombo on the home page', function (): void {
    $html = $this->get('/en')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>Tuk Tuk Rental in Negombo, Sri Lanka · '.e(config('app.name')).'</title>')
        ->toMatch('#<meta name="description" content="[^"]*Negombo#')
        ->toMatch('#<h1 class="hero__title">[^<]*<span[^>]*>tuk tuk.*?</span> in Negombo#s');

    $business = collect(jsonLdBlocks($html))->firstWhere('@type', 'AutoRental');
    expect($business['address']['addressLocality'])->toBe('Negombo')
        ->and($business['areaServed'][0])->toBe(['@type' => 'City', 'name' => 'Negombo']);
});

it('drops filter and tracking query strings from the canonical URL but keeps the page number', function (): void {
    $this->get('/en/tuk-tuks?category=3&utm_source=fb')
        ->assertSee('<link rel="canonical" href="'.seoBase().'/en/tuk-tuks">', false);

    $this->get('/en/blog?page=2')
        ->assertSee('<link rel="canonical" href="'.seoBase().'/en/blog?page=2">', false);
});

it('keeps the booking flow and the control panel out of search engines', function (): void {
    $booking = $this->get('/en/booking/start')->assertOk();

    expect($booking->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    $booking->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('rel="canonical"', false)
        ->assertDontSee('application/ld+json', false);

    expect($this->get('/control-panel/login')->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('links each language version of a record by its own translated slug', function (): void {
    $tukTuk = Vehicle::factory()->create(['name' => ['en' => 'Red Rocket', 'de' => 'Rote Rakete']]);
    $tukTuk->load('routeSlugs');

    $html = $this->get('/en/tuk-tuks/'.$tukTuk->slugFor('en'))->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="alternate" hreflang="de" href="'.seoBase().'/de/tuk-tuks/rote-rakete">')
        // The language switcher follows the same translated slug.
        ->toContain('href="'.seoBase().'/de/tuk-tuks/rote-rakete" hreflang="de"');

    $types = array_column(jsonLdBlocks($html), '@type');
    expect($types)->toContain('BreadcrumbList');
});

it('describes a stay with its location and a package with its starting price', function (): void {
    $cabana = Vehicle::factory()->stay()->create(['name' => ['en' => 'Sunset Cabana']]);
    $stay = collect(jsonLdBlocks($this->get('/en/stays/'.$cabana->slugFor('en'))->getContent()))->firstWhere('@type', 'Accommodation');

    expect($stay['geo'])->toBe(['@type' => 'GeoCoordinates', 'latitude' => 6.8406, 'longitude' => 81.8368])
        ->and($stay['occupancy']['maxValue'])->toBe(2);

    $package = Package::factory()->create(['name' => ['en' => 'Island Explorer'], 'is_active' => true]);
    $package->pricingTiers()->create(['min_days' => 1, 'max_days' => 6, 'price' => 30]);
    $package->pricingTiers()->create(['min_days' => 7, 'max_days' => null, 'price' => 25]);

    $product = collect(jsonLdBlocks($this->get('/en/packages/'.$package->slugFor('en'))->getContent()))->firstWhere('@type', 'Product');

    expect($product['offers']['lowPrice'])->toEqual(25)
        ->and($product['offers']['highPrice'])->toEqual(30);
});

it('cannot be broken out of a JSON-LD block by text entered in the admin', function (): void {
    $tukTuk = Vehicle::factory()->create(['name' => ['en' => 'Evil</script><script>alert(1)</script>']]);

    $html = $this->get('/en/tuk-tuks/'.$tukTuk->slugFor('en'))->getContent();

    expect($html)->not->toContain('<script>alert(1)')
        ->and(jsonLdBlocks($html)[0]['itemListElement'][2]['name'])->toBe('Evil</script><script>alert(1)</script>');
});

it('marks up the FAQ page as an FAQPage', function (): void {
    Faq::factory()->create(['question' => ['en' => 'Do I need a permit?'], 'answer' => ['en' => 'Yes, a Sri Lankan recognition permit.'], 'is_active' => true]);

    $faqPage = collect(jsonLdBlocks($this->get('/en/faq')->getContent()))->firstWhere('@type', 'FAQPage');

    expect($faqPage['mainEntity'][0]['name'])->toBe('Do I need a permit?')
        ->and($faqPage['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Yes, a Sri Lankan recognition permit.');
});

it('serves robots.txt pointing at the sitemap', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /control-panel/')
        ->assertSee('Sitemap: '.seoBase().'/sitemap.xml');
});

it('lists every public page in every language in sitemap.xml', function (): void {
    Vehicle::factory()->stay()->create(['name' => ['en' => 'Sunset Cabana', 'de' => 'Sonnenuntergang Cabana']]);
    Vehicle::factory()->retired()->create(['name' => ['en' => 'Old Banger']]);
    Package::factory()->create(['name' => ['en' => 'Surf & Stay'], 'is_active' => true]);

    $xml = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    $sitemap = simplexml_load_string($xml);
    expect($sitemap)->not->toBeFalse();

    expect($xml)
        ->toContain('<loc>'.seoBase().'/en/stays/sunset-cabana</loc>')
        ->toContain('<loc>'.seoBase().'/de/stays/sonnenuntergang-cabana</loc>')
        ->toContain('hreflang="de" href="'.seoBase().'/de/stays/sonnenuntergang-cabana"')
        ->toContain('<loc>'.seoBase().'/en/packages/surf-stay</loc>')
        ->toContain('<loc>'.seoBase().'/de/tuk-tuks</loc>')
        ->not->toContain('old-banger');
});
