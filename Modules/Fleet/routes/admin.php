<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Fleet\Http\Controllers\Admin\VehicleCategoryController;
use Modules\Fleet\Http\Controllers\Admin\VehicleController;
use Modules\Fleet\Http\Controllers\Admin\VehicleMaintenanceLogController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Fleet\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('fleet')->name('fleet.')->group(function (): void {
    Route::prefix('categories')->name('categories.')->group(function (): void {
        Route::get('/', [VehicleCategoryController::class, 'index'])->name('index');
        Route::get('/create', [VehicleCategoryController::class, 'create'])->name('create');
        Route::post('/', [VehicleCategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [VehicleCategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [VehicleCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [VehicleCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('vehicles')->name('vehicles.')->group(function (): void {
        Route::get('/', [VehicleController::class, 'index'])->name('index');
        Route::get('/create', [VehicleController::class, 'create'])->name('create');
        Route::post('/', [VehicleController::class, 'store'])->name('store');
        Route::get('/{vehicle}/edit', [VehicleController::class, 'edit'])->name('edit');
        Route::put('/{vehicle}', [VehicleController::class, 'update'])->name('update');
        Route::delete('/{vehicle}', [VehicleController::class, 'destroy'])->name('destroy');
        Route::delete('/{vehicle}/images/{image}', [VehicleController::class, 'destroyImage'])->name('images.destroy');
        Route::post('/{vehicle}/maintenance-logs', [VehicleMaintenanceLogController::class, 'store'])->name('maintenance-logs.store');
        Route::delete('/{vehicle}/maintenance-logs/{log}', [VehicleMaintenanceLogController::class, 'destroy'])->name('maintenance-logs.destroy');
    });
});
