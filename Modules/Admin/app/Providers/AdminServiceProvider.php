<?php

declare(strict_types=1);

namespace Modules\Admin\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Admin\Policies\UserPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AdminServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Admin';

    protected string $nameLower = 'admin';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        // Merge this module's config BEFORE registering the nested
        // RouteServiceProvider below: nwidart-modules registers module
        // providers after the app has already finished booting, which
        // means a nested provider's register() (and the route loading it
        // triggers) runs synchronously, inline, *before* this provider's
        // own boot() — where config merging normally happens — gets a
        // chance to run. Admin\Providers\RouteServiceProvider reads
        // config('admin.path') while building route groups, so without
        // this the prefix would be null the first time routes load.
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), $this->nameLower);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);

        Gate::policy(User::class, UserPolicy::class);
    }
}
