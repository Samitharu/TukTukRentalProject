<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Regression coverage for resources/views/errors/*.blade.php (brief §8:
 * "friendly error pages"). These must render successfully with NO
 * database, NO active locale, and NO matched route at all — that is
 * exactly the situation they exist to handle. A real bug shipped here
 * once already: resources/views/errors/minimal.blade.php shadowed
 * Laravel's own built-in errors::minimal layout (used internally by its
 * default 401/402/403/419/429 templates), turning a plain 403 into a 500
 * everywhere in the app. It was renamed to _layout.blade.php — this test
 * guards against that collision (or an equivalent one) recurring.
 */
it('renders a 404 page for a completely unmatched path, with no locale prefix at all', function (): void {
    $response = $this->get('/this-path-does-not-exist-anywhere');

    $response->assertNotFound();
    $response->assertSee("We can't find that page");
});

it('renders Laravel default 40x pages without the app error layout breaking them', function (): void {
    // A route deliberately outside any {locale} group and outside `admin`
    // middleware, so it exercises the framework's own default 403 view —
    // the one the earlier minimal.blade.php collision silently broke
    // (that vendor template extends errors::minimal internally, expecting
    // different variables than this app's own partial of the same name
    // once provided — see _layout.blade.php's docblock).
    Route::get('/__test-forbidden', fn () => abort(403))->middleware('web');

    $response = $this->get('/__test-forbidden');

    $response->assertForbidden();
});
