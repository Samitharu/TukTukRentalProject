<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    {{--
        Deliberately self-contained, not the public site layout. An error
        page must never itself throw — but the public layout's nav calls
        route('fleet.index') etc., which needs a `locale` URL default that
        Modules\Localization\Http\Middleware\SetLocale sets; that
        middleware never runs for a request that fails to match ANY route
        (an inactive-locale prefix, a truly unmatched path), which turned
        a 404 into a secondary 500 during rendering. Plain HTML has no such
        dependency, so it renders correctly in every failure mode.

        Named "_layout", not "minimal": Laravel's own framework package
        ships a default errors::minimal view (vendor/laravel/framework/src/
        Illuminate/Foundation/Exceptions/views/minimal.blade.php) used
        internally by its built-in 401/402/403/419/429 templates. A
        same-named file here shadows it app-wide — breaking every status
        code that relies on Laravel's default rather than a custom view in
        this directory (e.g. 403, which this app has no template for).
    --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f6f3ee; color: #1f2a25; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1.5rem; }
        .box { max-width: 28rem; text-align: center; }
        h1 { font-size: 1.5rem; margin-bottom: 0.75rem; }
        p { color: #5b6b64; line-height: 1.6; }
        a { display: inline-block; margin-top: 1rem; background: #1f7a5c; color: #fff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 999px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ $title }}</h1>
        <p>{{ $body }}</p>
        @isset($actionUrl)
            <a href="{{ $actionUrl }}">{{ $actionLabel }}</a>
        @endisset
    </div>
</body>
</html>
