<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Front\BookingFlowController;

// Loaded by Modules\Localization\Services\LocaleRouteRegistrar inside the
// {locale}-prefixed group. The review step's price recalculation is rate
// limited the same as other public write endpoints (brief §8).
Route::prefix('booking')->name('booking.')->group(function (): void {
    Route::get('start', [BookingFlowController::class, 'start'])->name('start');
    Route::post('start', [BookingFlowController::class, 'storeDates'])->name('start.store');

    Route::get('package', [BookingFlowController::class, 'package'])->name('package');
    Route::post('package', [BookingFlowController::class, 'storePackage'])->name('package.store');

    Route::get('addons', [BookingFlowController::class, 'addons'])->name('addons');
    Route::post('addons', [BookingFlowController::class, 'storeAddons'])->name('addons.store');

    Route::get('details', [BookingFlowController::class, 'details'])->name('details');
    Route::post('details', [BookingFlowController::class, 'storeDetails'])->name('details.store');

    Route::get('review', [BookingFlowController::class, 'review'])->name('review');
    Route::post('review/recalculate', [BookingFlowController::class, 'recalculate'])->middleware('throttle:20,1')->name('review.recalculate');

    Route::post('confirm', [BookingFlowController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
    Route::get('confirmation/{reference}', [BookingFlowController::class, 'confirmation'])->name('confirmation');
    Route::get('confirmation/{reference}/receipt', [BookingFlowController::class, 'receipt'])->middleware('throttle:20,1')->name('receipt');
    Route::post('confirmation/{reference}/feedback', [BookingFlowController::class, 'storeFeedback'])->middleware('throttle:5,1')->name('feedback.store');
});
