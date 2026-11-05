<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\Admin\BlogPostController;
use Modules\CMS\Http\Controllers\Admin\ContactMessageController;
use Modules\CMS\Http\Controllers\Admin\FaqController;
use Modules\CMS\Http\Controllers\Admin\PageController;
use Modules\CMS\Http\Controllers\Admin\ReviewController;

// Prefixed with config('admin.path') and named 'admin.' by
// Modules\CMS\Providers\RouteServiceProvider::mapAdminRoutes().
Route::resource('pages', PageController::class)->except('show');
Route::resource('faqs', FaqController::class)->except('show');

Route::prefix('reviews')->name('reviews.')->group(function (): void {
    Route::get('/', [ReviewController::class, 'index'])->name('index');
    Route::post('/{review}/toggle-approval', [ReviewController::class, 'toggleApproval'])->name('toggle-approval');
    Route::delete('/{review}', [ReviewController::class, 'destroy'])->name('destroy');
});

Route::prefix('messages')->name('messages.')->group(function (): void {
    Route::get('/', [ContactMessageController::class, 'index'])->name('index');
    Route::get('/{contactMessage}', [ContactMessageController::class, 'show'])->name('show');
    Route::post('/{contactMessage}/unread', [ContactMessageController::class, 'markUnread'])->name('unread');
    Route::delete('/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('destroy');
});

Route::prefix('blog')->name('blog.')->group(function (): void {
    Route::get('/', [BlogPostController::class, 'index'])->name('index');
    Route::get('/create', [BlogPostController::class, 'create'])->name('create');
    Route::post('/', [BlogPostController::class, 'store'])->name('store');
    Route::get('/{post}/edit', [BlogPostController::class, 'edit'])->name('edit');
    Route::put('/{post}', [BlogPostController::class, 'update'])->name('update');
    Route::delete('/{post}', [BlogPostController::class, 'destroy'])->name('destroy');
});
