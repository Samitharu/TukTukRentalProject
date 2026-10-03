<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\Front\BlogController;
use Modules\CMS\Http\Controllers\Front\ContactController;
use Modules\CMS\Http\Controllers\Front\FaqController;
use Modules\CMS\Http\Controllers\Front\PageController;

// Loaded by Modules\Localization\Services\LocaleRouteRegistrar inside the
// {locale}-prefixed group. Deliberately loaded LAST across all modules (see
// that class) because the bare `{slug}` catch-all at the bottom of this
// file must never get a chance to shadow another module's more specific
// route — and within this file, that catch-all is itself kept last too.
Route::get('faq', [FaqController::class, 'index'])->name('faq');

Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('contact', [ContactController::class, 'show'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');

Route::get('{slug}', [PageController::class, 'show'])->name('pages.show');
