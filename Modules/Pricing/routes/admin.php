<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pricing\Http\Controllers\Admin\CurrencyController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Pricing\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('currencies')->name('currencies.')->group(function (): void {
    Route::get('/', [CurrencyController::class, 'index'])->name('index');
    Route::post('/rate', [CurrencyController::class, 'updateRate'])->name('rate.update');
});
