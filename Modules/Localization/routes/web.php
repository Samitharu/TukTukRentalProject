<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Localization\Http\Controllers\RedirectLocaleController;

Route::get('/', RedirectLocaleController::class)->name('home.redirect');
