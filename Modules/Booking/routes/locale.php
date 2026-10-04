<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Front\BookingFlowController;

// Loaded by Modules\Localization\Services\LocaleRouteRegistrar inside the
// {locale}-prefixed group. Every public write endpoint is rate limited
// per IP (brief §8): step submissions each write the session (database
// driver), and confirmation pages show customer details, so neither may be
// hammered without limit. Infrastructure-level protection (CDN/WAF rate
// limiting) is still what stops a real DDoS.
Route::prefix('booking')->name('booking.')->group(function (): void {
    Route::get('start', [BookingFlowController::class, 'start'])->name('start');
    Route::post('start', [BookingFlowController::class, 'storeDates'])->middleware('throttle:30,1')->name('start.store');

    Route::get('package', [BookingFlowController::class, 'package'])->name('package');
    Route::post('package', [BookingFlowController::class, 'storePackage'])->middleware('throttle:30,1')->name('package.store');

    Route::get('addons', [BookingFlowController::class, 'addons'])->name('addons');
    Route::post('addons', [BookingFlowController::class, 'storeAddons'])->middleware('throttle:30,1')->name('addons.store');

    Route::get('details', [BookingFlowController::class, 'details'])->name('details');
    Route::post('details', [BookingFlowController::class, 'storeDetails'])->middleware('throttle:30,1')->name('details.store');

    Route::get('review', [BookingFlowController::class, 'review'])->name('review');
    Route::post('review/recalculate', [BookingFlowController::class, 'recalculate'])->middleware('throttle:20,1')->name('review.recalculate');

    Route::post('confirm', [BookingFlowController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
    Route::get('confirmation/{reference}', [BookingFlowController::class, 'confirmation'])->middleware('throttle:60,1')->name('confirmation');
    Route::get('status/{reference}', [BookingFlowController::class, 'status'])->name('status');
    Route::get('confirmation/{reference}/receipt', [BookingFlowController::class, 'receipt'])->middleware('throttle:20,1')->name('receipt');
    Route::post('confirmation/{reference}/feedback', [BookingFlowController::class, 'storeFeedback'])->middleware('throttle:5,1')->name('feedback.store');
    Route::post('feedback', [BookingFlowController::class, 'storeHomepageFeedback'])->middleware('throttle:5,1')->name('feedback.home.store');
});
