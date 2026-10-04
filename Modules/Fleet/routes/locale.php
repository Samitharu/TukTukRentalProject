<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Fleet\Http\Controllers\Front\FleetController;

// Loaded by Modules\Localization\Services\LocaleRouteRegistrar inside the
// {locale}-prefixed group.
Route::get('tuk-tuks', [FleetController::class, 'index'])->name('fleet.index');
Route::get('tuk-tuks/{slug}', [FleetController::class, 'show'])->name('fleet.show');
Route::get('stays', [FleetController::class, 'stays'])->name('stays.index');
Route::get('stays/{slug}', [FleetController::class, 'showStay'])->name('stays.show');
