<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\Admin\Auth\LoginController;
use Modules\Admin\Http\Controllers\Admin\Auth\TwoFactorController;
use Modules\Admin\Http\Controllers\Admin\DashboardController;
use Modules\Admin\Http\Controllers\Admin\UserController;

// This file is loaded twice, under two different middleware stacks, by
// Modules\Admin\Providers\RouteServiceProvider — see mapAdminRoutes() /
// mapGuestAdminRoutes(). $isGuestGroup distinguishes which pass we're in.
if (($isGuestGroup ?? false) === true) {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.attempt');

    return;
}

Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::prefix('2fa')->name('2fa.')->group(function (): void {
    Route::get('setup', [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('setup', [TwoFactorController::class, 'confirm'])->name('confirm');
    Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
    Route::get('challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
});

Route::get('/', DashboardController::class)->name('dashboard');

Route::resource('users', UserController::class)->except('show');
