<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pricing\Http\Controllers\Admin\CouponController;
use Modules\Pricing\Http\Controllers\Admin\CurrencyController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Pricing\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('coupons')->name('coupons.')->group(function (): void {
    Route::get('/', [CouponController::class, 'index'])->name('index');
    Route::get('/create', [CouponController::class, 'create'])->name('create');
    Route::post('/', [CouponController::class, 'store'])->name('store');
    Route::get('/{coupon}/edit', [CouponController::class, 'edit'])->name('edit');
    Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
    Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy');
});

Route::prefix('currencies')->name('currencies.')->group(function (): void {
    Route::get('/', [CurrencyController::class, 'index'])->name('index');
    Route::post('/', [CurrencyController::class, 'store'])->name('store');
    Route::post('/{currency}/rate', [CurrencyController::class, 'updateRate'])->name('rate.update');
    Route::post('/{currency}/make-base', [CurrencyController::class, 'makeBase'])->name('make-base');
    Route::delete('/{currency}', [CurrencyController::class, 'destroy'])->name('destroy');
});
