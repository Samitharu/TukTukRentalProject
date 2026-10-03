<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Admin\Http\Middleware\EnforceTwoFactor;
use Modules\Admin\Http\Middleware\EnsureAdminSessionIsFresh;
use Modules\Admin\Http\Middleware\RestrictAdminIpAllowlist;
use Modules\Core\Http\Middleware\SecurityHeaders;
use Modules\Localization\Http\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'locale' => SetLocale::class,
            'admin.ip' => RestrictAdminIpAllowlist::class,
            'admin.session' => EnsureAdminSessionIsFresh::class,
            'admin.2fa' => EnforceTwoFactor::class,
        ]);

        $middleware->group('admin', [
            'web',
            'admin.ip',
            'auth',
            'admin.session',
            'admin.2fa',
        ]);

        $middleware->throttleApi();

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
