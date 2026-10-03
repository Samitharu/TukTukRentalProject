<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\Admin\CustomerController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\Customer\Providers\RouteServiceProvider::mapAdminRoutes().
Route::prefix('customers')->name('customers.')->group(function (): void {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
});
