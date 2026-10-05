<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Package\Http\Controllers\Front\PackageController;

// Loaded by Modules\Localization\Services\LocaleRouteRegistrar inside the
// {locale}-prefixed group.
Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
Route::get('pricing', [PackageController::class, 'pricing'])->name('pricing');
Route::get('packages/category/{slug}', [PackageController::class, 'category'])->name('packages.category');
Route::get('packages/{slug}', [PackageController::class, 'show'])->name('packages.show');
