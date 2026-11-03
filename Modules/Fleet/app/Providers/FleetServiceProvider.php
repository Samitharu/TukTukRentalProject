<?php

declare(strict_types=1);

namespace Modules\Fleet\Providers;

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
}
