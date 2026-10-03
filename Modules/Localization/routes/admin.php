<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Localization\Http\Controllers\Admin\LocaleController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Localization\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('locales')->name('locales.')->group(function (): void {
    Route::get('/', [LocaleController::class, 'index'])->name('index');
    Route::get('/create', [LocaleController::class, 'create'])->name('create');
    Route::post('/', [LocaleController::class, 'store'])->name('store');
    Route::get('/{locale}/edit', [LocaleController::class, 'edit'])->name('edit');
    Route::put('/{locale}', [LocaleController::class, 'update'])->name('update');
    Route::post('/{locale}/make-default', [LocaleController::class, 'makeDefault'])->name('make-default');
    Route::delete('/{locale}', [LocaleController::class, 'destroy'])->name('destroy');
});
