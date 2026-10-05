<?php

declare(strict_types=1);

namespace Modules\Package\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Package\Models\Addon;
use Modules\Package\Models\Package;
use Modules\Package\Models\ProductCategory;
use Modules\Package\Policies\AddonPolicy;
use Modules\Package\Policies\PackagePolicy;
use Modules\Package\Policies\ProductCategoryPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PackageServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Package';

    protected string $nameLower = 'package';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Package::class, PackagePolicy::class);
        Gate::policy(Addon::class, AddonPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
    }
}
