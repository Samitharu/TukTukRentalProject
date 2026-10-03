<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModuleFactoryResolver();
    }

    /**
     * Laravel's default factory-name guesser assumes `App\Models\X` →
     * `Database\Factories\XFactory`. Every domain model instead lives at
     * `Modules\{Module}\Models\X`, with its factory at
     * `Modules\{Module}\Database\Factories\XFactory` — this teaches the
     * guesser that convention once, for every module, instead of each
     * model needing its own `newFactory()` override.
     */
    private function registerModuleFactoryResolver(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            if (preg_match('/^Modules\\\\([^\\\\]+)\\\\Models\\\\(.+)$/', $modelName, $matches) === 1) {
                return "Modules\\{$matches[1]}\\Database\\Factories\\{$matches[2]}Factory";
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }
}
