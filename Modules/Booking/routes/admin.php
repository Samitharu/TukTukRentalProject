<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Admin\BookingActionController;
use Modules\Booking\Http\Controllers\Admin\BookingController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Booking\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('bookings')->name('bookings.')->group(function (): void {
    Route::get('/', [BookingController::class, 'index'])->name('index');
    Route::get('/create', [BookingController::class, 'create'])->name('create');
    Route::post('/', [BookingController::class, 'store'])->name('store');
    Route::get('/{booking}', [BookingController::class, 'show'])->name('show');

    Route::post('/{booking}/cancel', [BookingActionController::class, 'cancel'])->name('cancel');
    Route::post('/{booking}/activate', [BookingActionController::class, 'activate'])->name('activate');
    Route::post('/{booking}/complete', [BookingActionController::class, 'complete'])->name('complete');
    Route::post('/{booking}/no-show', [BookingActionController::class, 'noShow'])->name('no-show');
    Route::put('/{booking}/dates', [BookingActionController::class, 'changeDates'])->name('dates.update');
    Route::put('/{booking}/vehicle', [BookingActionController::class, 'reassignVehicle'])->name('vehicle.update');
    Route::put('/{booking}/odometer', [BookingActionController::class, 'recordOdometer'])->name('odometer.update');
});
