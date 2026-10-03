<?php

declare(strict_types=1);

namespace Modules\Localization\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Localization\Models\Locale;
use Modules\Localization\Policies\LocalePolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LocalizationServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Localization';

    protected string $nameLower = 'localization';

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

        $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);

        Gate::policy(Locale::class, LocalePolicy::class);
    }
}
