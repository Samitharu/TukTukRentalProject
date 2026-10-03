<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Modules\Core\Console\Commands\MinifyAssets;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Core';

    protected string $nameLower = 'core';

    /**
     * @var string[]
     */
    protected array $commands = [
        MinifyAssets::class,
    ];

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

        // Explicit namespaced registration: the base ModuleServiceProvider
        // loads module lang files into the *unnamespaced* group when no
        // resources/lang/modules/{name} override exists, which risks
        // collisions across modules using the same file name (e.g. every
        // module having a "front.php"). Registering it again, namespaced,
        // is harmless (Laravel allows multiple namespaces per path) and is
        // what lets every view call __('core::front.key') reliably.
        $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);

        $this->registerResponseMacros();
    }

    /**
     * Consistent JSON envelope for the small JSON endpoints (pricing/availability
     * recalculation, etc.) that later modules expose — avoids every controller
     * hand-rolling its own success/error shape.
     */
    private function registerResponseMacros(): void
    {
        Response::macro('apiSuccess', function (mixed $data = [], int $status = 200): JsonResponse {
            return Response::json(['success' => true, 'data' => $data], $status);
        });

        Response::macro('apiError', function (string $message, int $status = 422, array $errors = []): JsonResponse {
            return Response::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
        });
    }
}
