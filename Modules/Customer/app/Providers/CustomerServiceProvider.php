<?php

declare(strict_types=1);

namespace Modules\Customer\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Customer\Models\Customer;
use Modules\Customer\Policies\CustomerPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CustomerServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Customer';

    protected string $nameLower = 'customer';

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

        Gate::policy(Customer::class, CustomerPolicy::class);
    }
}
