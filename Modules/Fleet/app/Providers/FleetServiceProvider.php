<?php

declare(strict_types=1);

namespace Modules\Fleet\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Modules\Fleet\Policies\VehicleCategoryPolicy;
use Modules\Fleet\Policies\VehiclePolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FleetServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Fleet';

    protected string $nameLower = 'fleet';

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

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(VehicleCategory::class, VehicleCategoryPolicy::class);
    }
}
