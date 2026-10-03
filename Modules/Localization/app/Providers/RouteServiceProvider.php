<?php

declare(strict_types=1);

namespace Modules\Localization\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Localization\Services\LocaleRouteRegistrar;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Localization';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapWebRoutes();
        $this->mapAdminRoutes();
        app(LocaleRouteRegistrar::class)->register();
    }

    /**
     * Unprefixed routes: the "which locale?" entry point (root redirect) —
     * this must run before a locale is known, so it cannot itself live
     * inside the locale-prefixed group.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    protected function mapAdminRoutes(): void
    {
        Route::middleware('admin')
            ->prefix(config('admin.path'))
            ->name('admin.')
            ->group(module_path($this->name, '/routes/admin.php'));
    }
}
