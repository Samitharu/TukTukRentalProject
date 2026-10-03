<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Package\Http\Controllers\Admin\AddonController;
use Modules\Package\Http\Controllers\Admin\PackageAddonController;
use Modules\Package\Http\Controllers\Admin\PackageController;
use Modules\Package\Http\Controllers\Admin\PackagePricingTierController;
use Modules\Package\Http\Controllers\Admin\PackageSeasonController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Package\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('packages')->name('packages.')->group(function (): void {
    Route::get('/', [PackageController::class, 'index'])->name('index');
    Route::get('/create', [PackageController::class, 'create'])->name('create');
    Route::post('/', [PackageController::class, 'store'])->name('store');
    Route::get('/{package}/edit', [PackageController::class, 'edit'])->name('edit');
    Route::put('/{package}', [PackageController::class, 'update'])->name('update');
    Route::delete('/{package}', [PackageController::class, 'destroy'])->name('destroy');
    Route::delete('/{package}/images/{image}', [PackageController::class, 'destroyImage'])->name('images.destroy');

    Route::post('/{package}/pricing-tiers', [PackagePricingTierController::class, 'store'])->name('pricing-tiers.store');
    Route::delete('/{package}/pricing-tiers/{tier}', [PackagePricingTierController::class, 'destroy'])->name('pricing-tiers.destroy');

    Route::post('/{package}/seasons', [PackageSeasonController::class, 'store'])->name('seasons.store');
    Route::delete('/{package}/seasons/{season}', [PackageSeasonController::class, 'destroy'])->name('seasons.destroy');

    Route::put('/{package}/addons', [PackageAddonController::class, 'update'])->name('addons.update');
});

Route::prefix('addons')->name('addons.')->group(function (): void {
    Route::get('/', [AddonController::class, 'index'])->name('index');
    Route::get('/create', [AddonController::class, 'create'])->name('create');
    Route::post('/', [AddonController::class, 'store'])->name('store');
    Route::get('/{addon}/edit', [AddonController::class, 'edit'])->name('edit');
    Route::put('/{addon}', [AddonController::class, 'update'])->name('update');
    Route::delete('/{addon}', [AddonController::class, 'destroy'])->name('destroy');
});
