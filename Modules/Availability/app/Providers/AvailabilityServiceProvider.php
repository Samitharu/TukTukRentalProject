<?php

declare(strict_types=1);

namespace Modules\Availability\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Availability\Models\TransferArea;
use Modules\Availability\Policies\TransferAreaPolicy;
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

        Gate::policy(TransferArea::class, TransferAreaPolicy::class);
    }
}
