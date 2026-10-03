<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Front\HomeController;

// Loaded by Modules\Localization\Services\LocaleRouteRegistrar inside the
// {locale}-prefixed group — do not add this route to routes/web.php.
Route::get('/', HomeController::class)->name('home');
