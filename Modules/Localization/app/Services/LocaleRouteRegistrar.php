<?php

declare(strict_types=1);

namespace Modules\Localization\Services;

use Illuminate\Support\Facades\Route;

/**
 * Registers a single `/{locale}/...` route group that `require`-s every
 * enabled module's `routes/locale.php` (a file separate from that module's
 * own `routes/web.php`, which stays unprefixed for routes — e.g. admin —
 * that must never be locale-prefixed). This is what lets a new language go
 * live from the admin with zero route-file changes: see docs/01-architecture.md §4.
 *
 * The `{locale}` segment is matched by a generic ISO-639-1/639-2 shaped
 * pattern here, deliberately NOT built from the current active-locales list
 * in the database: route *registration* happens once at application boot
 * (before any request, and in tests before that test's data is seeded), so
 * gating it on live DB state would make it stale. Whether a given code is
 * actually an active locale is instead checked on every request by
 * Modules\Localization\Http\Middleware\SetLocale, which 404s on anything
 * not currently active — that check is always fresh.
 */
final class LocaleRouteRegistrar
{
    private const string LOCALE_PATTERN = '[a-zA-Z]{2,3}';

    /**
     * Modules whose locale.php defines a catch-all pattern (e.g. CMS's
     * generic `/{slug}` page route) must load after every module with
     * more specific routes — Laravel matches routes in registration
     * order, and the plain glob() below returns modules alphabetically,
     * which would put CMS ahead of Fleet/Package and let `/{slug}`
     * swallow `/tuk-tuks` before Fleet's own route ever runs. Listed
     * modules are moved to the end, in this order, after the alphabetical
     * scan; everything else keeps its natural (alphabetical) order.
     *
     * @var string[]
     */
    private const array LOAD_LAST = ['CMS'];

    public function register(): void
    {
        Route::prefix('{locale}')
            ->where(['locale' => self::LOCALE_PATTERN])
            ->middleware(['web', 'locale'])
            ->group(function (): void {
                foreach ($this->orderedLocaleRouteFiles() as $localeRoutes) {
                    require $localeRoutes;
                }
            });
    }

    /**
     * @return string[]
     */
    private function orderedLocaleRouteFiles(): array
    {
        $files = glob(base_path('Modules/*/routes/locale.php')) ?: [];

        $deferred = [];
        $normal = [];

        foreach ($files as $file) {
            $moduleName = basename(dirname($file, 2));
            in_array($moduleName, self::LOAD_LAST, true) ? $deferred[] = $file : $normal[] = $file;
        }

        usort($deferred, fn (string $a, string $b) => array_search(basename(dirname($a, 2)), self::LOAD_LAST, true)
            <=> array_search(basename(dirname($b, 2)), self::LOAD_LAST, true));

        return [...$normal, ...$deferred];
    }
}
