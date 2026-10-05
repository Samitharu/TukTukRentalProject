<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Modules\CMS\Models\BlogPost;
use Modules\CMS\Models\Page;
use Modules\Fleet\Models\Vehicle;
use Modules\Localization\Models\Locale;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;

/**
 * /sitemap.xml and /robots.txt. Same deliberate cross-module reach as
 * HomeController: a sitemap lists every public page of every module.
 *
 * Each URL carries <xhtml:link rel="alternate" hreflang> entries for the
 * same page in every other language (Google's recommended way to tie
 * translations together), using each record's own translated slug.
 */
final class SitemapController extends Controller
{
    /** Listing pages, identical path in every language. */
    private const array STATIC_ROUTES = ['home', 'fleet.index', 'stays.index', 'packages.index', 'pricing', 'faq', 'blog.index', 'contact'];

    private const int CACHE_SECONDS = 3600;

    public function sitemap(): Response
    {
        $xml = Cache::remember('seo.sitemap.'.request()->getHost(), self::CACHE_SECONDS, fn () => view('core::seo.sitemap', [
            'groups' => $this->urlGroups(),
            'defaultLocale' => Locale::defaultCode(),
        ])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // The control panel is also noindex'd; disallowing it just saves
            // crawl budget. The booking flow is intentionally NOT listed —
            // see Modules\Core\Http\Middleware\NoIndex.
            'Disallow: /'.trim((string) config('admin.path', 'control-panel'), '/').'/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Every page as a group of language versions: [locale => url] plus the
     * record's last-modified time when there is one.
     *
     * @return array<int, array{urls: array<string, string>, lastmod: ?string}>
     */
    private function urlGroups(): array
    {
        $locales = Locale::activeCached()->pluck('code')->all();
        $groups = [];

        foreach (self::STATIC_ROUTES as $routeName) {
            $groups[] = [
                'urls' => array_combine($locales, array_map(fn (string $code) => route($routeName, ['locale' => $code]), $locales)),
                'lastmod' => null,
            ];
        }

        $records = [
            ...Vehicle::query()->active()->with(['category', 'routeSlugs'])->get()
                ->map(fn (Vehicle $unit) => [$unit, $unit->isStay() ? 'stays.show' : 'fleet.show']),
            ...ProductCategory::query()->active()->with('routeSlugs')->get()
                ->map(fn (ProductCategory $category) => [$category, 'packages.category']),
            ...Package::query()->active()->currentlyValid()->inVisibleCategory()->with('routeSlugs')->get()
                ->map(fn (Package $package) => [$package, 'packages.show']),
            ...BlogPost::query()->published()->with('routeSlugs')->get()
                ->map(fn (BlogPost $post) => [$post, 'blog.show']),
            ...Page::query()->published()->with('routeSlugs')->get()
                ->map(fn (Page $page) => [$page, 'pages.show']),
        ];

        foreach ($records as [$model, $routeName]) {
            $urls = $this->modelUrls($model, $routeName, $locales);

            if ($urls !== []) {
                $groups[] = ['urls' => $urls, 'lastmod' => $model->updated_at?->toAtomString()];
            }
        }

        return $groups;
    }

    /**
     * @param  string[]  $locales
     * @return array<string, string>
     */
    private function modelUrls(Model $model, string $routeName, array $locales): array
    {
        $urls = [];

        foreach ($locales as $code) {
            $slug = $model->slugFor($code);

            if ($slug !== null) {
                $urls[$code] = route($routeName, ['locale' => $code, 'slug' => $slug]);
            }
        }

        return $urls;
    }
}
