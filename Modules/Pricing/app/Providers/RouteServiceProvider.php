<?php

declare(strict_types=1);

namespace Modules\Pricing\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Pricing';

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
}
