<?php

declare(strict_types=1);

namespace Modules\Customer\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Customer';

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
