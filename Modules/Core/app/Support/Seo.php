<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Core\Models\SiteSetting;
use Modules\Localization\Models\Locale;

/**
 * Shared helpers behind <x-core::seo-head> and the sitemap: canonical and
 * per-language (hreflang) URLs, meta-description clean-up, and schema.org
 * JSON-LD building blocks.
 */
final class Seo
{
    public const int DESCRIPTION_LENGTH = 160;

    /** Open Graph wants language_TERRITORY; codes not listed pass through. */
    private const array OG_LOCALES = [
        'en' => 'en_US',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
        'ru' => 'ru_RU',
    ];

    /**
     * The current URL without tracking/filter query strings — so
     * `?category=3` or `?utm_source=…` don't count as separate pages —
     * keeping only `?page=N` (each listing page is its own canonical page).
     */
    public static function canonicalUrl(): string
    {
        $page = (int) request()->query('page', 1);

        return url()->current().($page > 1 ? '?page='.$page : '');
    }

    /**
     * The same page in every active language, for pages whose path is the
     * same in all of them (listings, home): only the /{locale} segment
     * differs. Pages with a translated slug pass forModel() instead.
     *
     * @return array<string, string>  locale code => absolute URL
     */
    public static function alternatesForCurrentPath(): array
    {
        $segments = explode('/', trim(request()->path(), '/'));
        $alternates = [];

        foreach (Locale::activeCached() as $locale) {
            $segments[0] = $locale->code;
            $alternates[$locale->code] = url('/'.implode('/', $segments));
        }

        return $alternates;
    }

    /**
     * A translated-slug record's URL in each language it has a slug for.
     * Expects `routeSlugs` eager-loaded (all locales) to avoid a query per
     * language.
     *
     * @param  Model&\Modules\Localization\Support\HasTranslatableSlug  $model
     * @return array<string, string>
     */
    public static function alternatesForModel(Model $model, string $routeName): array
    {
        $alternates = [];

        foreach (Locale::activeCached() as $locale) {
            $slug = $model->slugFor($locale->code);

            if ($slug !== null) {
                $alternates[$locale->code] = route($routeName, ['locale' => $locale->code, 'slug' => $slug]);
            }
        }

        return $alternates;
    }

    public static function defaultLocaleCode(): string
    {
        return Locale::defaultCode();
    }

    public static function ogLocale(string $code): string
    {
        return self::OG_LOCALES[$code] ?? $code;
    }

    /** Plain text, single-spaced, cut at a word boundary to ~160 chars. */
    public static function description(?string $text): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5)));

        return Str::limit($plain, self::DESCRIPTION_LENGTH, '…', preserveWords: true);
    }

    /** The share image used when a page has no photo of its own. */
    public static function defaultImage(): string
    {
        return SiteSetting::current()->heroImageUrl() ?? asset('assets/images/hero-tuktuk.jpg');
    }

    public static function storageImage(?string $path): ?string
    {
        return $path !== null && $path !== '' ? asset('storage/'.$path) : null;
    }

    /**
     * The business itself, as schema.org sees it — shown on the home page.
     * Placeholder contact details (config/core "[… pending]") are left out
     * rather than published as facts.
     *
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $business = config('core.business');
        $real = fn (?string $value) => is_string($value) && $value !== '' && ! str_starts_with($value, '[') && ! str_contains($value, '.example') && ! str_contains($value, '000 0000');
        $city = $real($business['city'] ?? null) ? $business['city'] : null;
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $real($business['address'] ?? null) ? $business['address'] : null,
            'addressLocality' => $city,
            'addressCountry' => 'LK',
        ], fn ($value) => $value !== null);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'AutoRental',
            '@id' => route('home').'#business',
            'name' => config('app.name'),
            'url' => route('home'),
            'logo' => SiteSetting::current()->logoUrl(),
            'image' => self::defaultImage(),
            'telephone' => $real($business['phone'] ?? null) ? $business['phone'] : null,
            'email' => $real($business['email'] ?? null) ? $business['email'] : null,
            'address' => $address,
            'areaServed' => $city !== null
                ? [['@type' => 'City', 'name' => $city], ['@type' => 'Country', 'name' => 'Sri Lanka']]
                : ['@type' => 'Country', 'name' => 'Sri Lanka'],
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => route('home'),
            'inLanguage' => app()->getLocale(),
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $trail  [name, url] pairs, home first
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $crumb, int $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb[0], 'item' => $crumb[1]],
                $trail,
                array_keys($trail),
            ),
        ];
    }

    /**
     * Safe inside <script type="application/ld+json">: JSON_HEX_TAG turns
     * "<" and ">" into < / >, so text from the admin (a name, a
     * description) can never close the script element.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function jsonLd(array $schema): string
    {
        return (string) json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
