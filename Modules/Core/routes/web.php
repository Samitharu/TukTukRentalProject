<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Front\SitemapController;

// Core provides shared middleware, the base Blade layout, and helpers
// consumed by every other module. Locale-prefixed public routes are
// registered per-module by the Localization module's route group
// registrar (see Modules/Localization); only the two site-wide,
// language-independent SEO files live here. public/robots.txt must not
// exist, or the web server serves that static file instead.
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');
