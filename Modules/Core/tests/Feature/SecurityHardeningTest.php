<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Modules\Availability\Models\BusinessLocation;
use Modules\CMS\Models\Review;
use Modules\Localization\Models\Locale;

/**
 * Cross-cutting security checks: password storage, CSRF coverage of every
 * state-changing route, rate limits on public write endpoints, security
 * headers, admin authorization, and output escaping of customer content.
 */
beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
});

// ---- Passwords --------------------------------------------------------------

it('stores staff passwords as bcrypt hashes, never in plain text', function (): void {
    $user = User::factory()->create(['password' => 'correct horse battery staple']);
    $stored = $user->getAttributes()['password'];

    expect($stored)->not->toBe('correct horse battery staple')
        ->and(Hash::info($stored)['algoName'])->toBe('bcrypt')
        ->and(Hash::check('correct horse battery staple', $stored))->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('password')
        ->and($user->toArray())->not->toHaveKey('two_factor_secret');
});

it('uses a production bcrypt cost of at least 12 rounds', function (): void {
    // phpunit.xml lowers BCRYPT_ROUNDS to 4 for speed; read the real value.
    $env = file_get_contents(base_path('.env.example'));

    expect((int) (preg_match('/^BCRYPT_ROUNDS=(\d+)/m', $env, $m) ? $m[1] : 0))->toBeGreaterThanOrEqual(12);
});

it('spends a password hash on unknown emails so login timing does not reveal accounts', function (): void {
    Hash::spy();

    $this->post('/control-panel/login', ['email' => 'nobody@example.test', 'password' => 'guess'])
        ->assertSessionHasErrors('email');

    Hash::shouldHaveReceived('make')->once()->with('guess');
});

// ---- CSRF -------------------------------------------------------------------

it('protects every state-changing web route with CSRF verification', function (): void {
    // Constructing the HTTP kernel is what registers the `web`/`admin`
    // middleware groups on the router, so they can be expanded below.
    app(\Illuminate\Contracts\Http\Kernel::class);
    $router = app('router');
    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== [])
        ->reject(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/')) // stateless, token-based
        ->reject(fn (RoutingRoute $route) => in_array(ValidateCsrfToken::class, $router->gatherRouteMiddleware($route), true))
        ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

it('rejects a form post without a CSRF token (419) outside the test environment', function (): void {
    // ValidateCsrfToken skips itself while running unit tests; switch the
    // environment so the real check runs.
    app()->detectEnvironment(fn () => 'production');

    $this->post('/en/contact', ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'spam'])
        ->assertStatus(419);
    $this->post('/control-panel/login', ['email' => 'a@b.test', 'password' => 'x'])
        ->assertStatus(419);
});

// ---- Rate limiting ----------------------------------------------------------

it('rate limits public write endpoints per client', function (string $uri, int $limit, array $payload): void {
    BusinessLocation::factory()->create(['is_active' => true]);

    for ($i = 0; $i < $limit; $i++) {
        expect($this->post($uri, $payload)->status())->not->toBe(429);
    }

    $this->post($uri, $payload)->assertStatus(429);
})->with([
    'admin login' => ['/control-panel/login', 10, ['email' => 'x@example.test', 'password' => 'nope']],
    'booking confirm' => ['/en/booking/confirm', 10, ['terms_accepted' => '1']],
    'booking date step' => ['/en/booking/start', 30, ['start_date' => 'bad']],
    'homepage review' => ['/en/booking/feedback', 5, ['reference' => 'MTR-AAAAAAAA', 'rating' => 5]],
    'contact form' => ['/en/contact', 5, ['name' => 'A']],
]);

it('keeps each route\'s limit independent — using one form never uses up another\'s', function (): void {
    // Previously all `throttle:N,M` routes shared one per-IP counter: 5 hits
    // anywhere (e.g. the booking steps) and the review form returned 429.
    for ($i = 0; $i < 20; $i++) {
        $this->post('/en/booking/start', ['start_date' => 'bad']);
    }

    expect($this->post('/en/booking/feedback', ['reference' => 'MTR-AAAAAAAA', 'rating' => 5])->status())->not->toBe(429)
        ->and($this->post('/en/contact', ['name' => 'A'])->status())->not->toBe(429);
});

it('does not reset a limit when the visitor switches language', function (): void {
    Locale::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => false, 'is_active' => true, 'sort_order' => 2]);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/en/booking/feedback', ['reference' => 'MTR-AAAAAAAA', 'rating' => 5]);
    }

    $this->post('/de/booking/feedback', ['reference' => 'MTR-AAAAAAAA', 'rating' => 5])->assertStatus(429);
});

// ---- Headers ----------------------------------------------------------------

it('sends hardened security headers on public and admin pages', function (string $uri): void {
    $response = $this->get($uri);

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/")
        ->not->toContain('unsafe-eval');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
})->with(['/en', '/control-panel/login']);

// ---- Admin authorization ----------------------------------------------------

it('sends guests to the login page from every admin screen', function (): void {
    $adminGetRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true)
            && str_starts_with($route->uri(), 'control-panel')
            && ! str_contains($route->uri(), '{')
            && $route->getName() !== 'admin.login');

    expect($adminGetRoutes)->not->toBeEmpty();

    foreach ($adminGetRoutes as $route) {
        $this->get('/'.$route->uri())->assertRedirect('/control-panel/login');
    }
});

it('forbids a staff account with no permissions from every admin screen except the dashboard', function (): void {
    $staff = User::factory()->create(['is_active' => true]);
    $open = ['admin.dashboard', 'admin.2fa.setup', 'admin.2fa.challenge', 'admin.2fa.recovery-codes'];

    $checked = 0;
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->uri(), 'control-panel')
            || str_contains($route->uri(), '{') || $route->getName() === 'admin.login' || in_array($route->getName(), $open, true)) {
            continue;
        }

        $this->actingAs($staff)->get('/'.$route->uri())->assertForbidden();
        $checked++;
    }

    expect($checked)->toBeGreaterThan(10);
});

// ---- Output escaping ----------------------------------------------------------

it('escapes customer-written review text on the public homepage', function (): void {
    Review::query()->create([
        'customer_name' => '<img src=x onerror=alert(1)>',
        'rating' => 5,
        'content' => '<script>alert("xss")</script>Great trip',
        'is_approved' => true,
    ]);

    $this->get('/en')
        ->assertOk()
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee('&lt;script&gt;', false);
});
