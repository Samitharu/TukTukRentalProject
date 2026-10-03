<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\BrandingController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Core\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('branding')->name('branding.')->group(function (): void {
    Route::get('/', [BrandingController::class, 'edit'])->name('edit');
    Route::post('/', [BrandingController::class, 'update'])->name('update');
    Route::delete('/logo', [BrandingController::class, 'removeLogo'])->name('logo.destroy');
    Route::delete('/hero-image', [BrandingController::class, 'removeHeroImage'])->name('hero-image.destroy');
});
