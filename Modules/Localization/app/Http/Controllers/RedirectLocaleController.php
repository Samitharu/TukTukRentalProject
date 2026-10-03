<?php

declare(strict_types=1);

namespace Modules\Localization\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Localization\Models\Locale;
use Modules\Localization\Support\CrawlerDetector;

/**
 * The "which locale?" entry point for any unprefixed URL (currently just
 * `/`). Resolution order: remembered cookie > bot (always default, never
 * guessed) > Accept-Language detection > default. Redirects exactly once;
 * the destination's own SetLocale middleware then takes over and refreshes
 * the cookie on every subsequent request.
 */
final class RedirectLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $active = Locale::activeCached();
        $activeCodes = $active->pluck('code')->all();

        $cookieLocale = $request->cookie('locale');
        if (is_string($cookieLocale) && in_array($cookieLocale, $activeCodes, true)) {
            return redirect('/'.$cookieLocale);
        }

        if (CrawlerDetector::isBot($request->userAgent())) {
            return redirect('/'.Locale::defaultCode());
        }

        $preferred = $this->detectFromAcceptLanguage($request, $activeCodes);

        return redirect('/'.($preferred ?? Locale::defaultCode()));
    }

    /**
     * @param string[] $activeCodes
     */
    private function detectFromAcceptLanguage(Request $request, array $activeCodes): ?string
    {
        $header = $request->server('HTTP_ACCEPT_LANGUAGE');

        if (! is_string($header) || $header === '') {
            return null;
        }

        $parsed = [];
        foreach (explode(',', $header) as $part) {
            $segments = explode(';q=', trim($part));
            $lang = mb_strtolower(explode('-', $segments[0])[0] ?? '');
            $quality = isset($segments[1]) ? (float) $segments[1] : 1.0;

            if ($lang !== '') {
                $parsed[$lang] = max($parsed[$lang] ?? 0.0, $quality);
            }
        }

        arsort($parsed);

        foreach (array_keys($parsed) as $lang) {
            if (in_array($lang, $activeCodes, true)) {
                return $lang;
            }
        }

        return null;
    }
}
