<?php

declare(strict_types=1);

namespace Modules\Admin\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Admin';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapGuestAdminRoutes();
        $this->mapAdminRoutes();
    }

    /**
     * Login screen — must NOT require auth (that would redirect back to
     * itself). Same prefix/name as the protected group so `route('admin.login')`
     * and `route('admin.dashboard')` read naturally from Blade/redirects.
     */
    protected function mapGuestAdminRoutes(): void
    {
        Route::middleware(['web', 'admin.ip'])
            ->prefix(config('admin.path'))
            ->name('admin.')
            ->group(function (): void {
                $isGuestGroup = true;
                require module_path($this->name, '/routes/admin.php');
            });
    }

    protected function mapAdminRoutes(): void
    {
        Route::middleware('admin')
            ->prefix(config('admin.path'))
            ->name('admin.')
            ->group(function (): void {
                $isGuestGroup = false;
                require module_path($this->name, '/routes/admin.php');
            });
    }
}
