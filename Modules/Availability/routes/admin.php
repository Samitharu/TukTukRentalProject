<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Availability\Http\Controllers\Admin\BusinessLocationController;
use Modules\Availability\Http\Controllers\Admin\TransferAreaController;

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

Route::prefix('transfer-areas')->name('transfer-areas.')->group(function (): void {
    Route::get('/', [TransferAreaController::class, 'index'])->name('index');
    Route::get('/create', [TransferAreaController::class, 'create'])->name('create');
    Route::post('/', [TransferAreaController::class, 'store'])->name('store');
    Route::get('/{area}/edit', [TransferAreaController::class, 'edit'])->name('edit');
    Route::put('/{area}', [TransferAreaController::class, 'update'])->name('update');
    Route::delete('/{area}', [TransferAreaController::class, 'destroy'])->name('destroy');
});
