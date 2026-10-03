<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Availability\Http\Controllers\Admin\AvailabilityBlackoutController;
use Modules\Availability\Http\Controllers\Admin\BusinessLocationController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Availability\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('locations')->name('locations.')->group(function (): void {
    Route::get('/', [BusinessLocationController::class, 'index'])->name('index');
    Route::get('/create', [BusinessLocationController::class, 'create'])->name('create');
    Route::post('/', [BusinessLocationController::class, 'store'])->name('store');
    Route::get('/{location}/edit', [BusinessLocationController::class, 'edit'])->name('edit');
    Route::put('/{location}', [BusinessLocationController::class, 'update'])->name('update');
    Route::delete('/{location}', [BusinessLocationController::class, 'destroy'])->name('destroy');
});

Route::prefix('blackouts')->name('blackouts.')->group(function (): void {
    Route::get('/', [AvailabilityBlackoutController::class, 'index'])->name('index');
    Route::post('/', [AvailabilityBlackoutController::class, 'store'])->name('store');
    Route::delete('/{blackout}', [AvailabilityBlackoutController::class, 'destroy'])->name('destroy');
});
