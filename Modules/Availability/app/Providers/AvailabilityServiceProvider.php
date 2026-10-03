<?php

declare(strict_types=1);

namespace Modules\Availability\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Availability\Models\AvailabilityBlackout;
use Modules\Availability\Models\BusinessLocation;
use Modules\Availability\Policies\AvailabilityBlackoutPolicy;
use Modules\Availability\Policies\BusinessLocationPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AvailabilityServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Availability';

    protected string $nameLower = 'availability';

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

        Gate::policy(BusinessLocation::class, BusinessLocationPolicy::class);
        Gate::policy(AvailabilityBlackout::class, AvailabilityBlackoutPolicy::class);
    }
}
