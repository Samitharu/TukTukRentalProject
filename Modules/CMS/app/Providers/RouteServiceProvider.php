<?php

declare(strict_types=1);

namespace Modules\CMS\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'CMS';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapAdminRoutes();
    }

    protected function mapAdminRoutes(): void
    {
        Route::middleware('admin')
            ->prefix(config('admin.path'))
            ->name('admin.')
            ->group(module_path($this->name, '/routes/admin.php'));
    }

    // Public routes (pages, FAQ, blog) are locale-prefixed and live in
    // routes/locale.php, loaded by Modules\Localization\Services\LocaleRouteRegistrar
    // — which deliberately loads CMS's generic `/{slug}` catch-all last, so
    // it can never shadow another module's more specific locale route
    // (e.g. Fleet's `/tuk-tuks`). See that class for why.
}
