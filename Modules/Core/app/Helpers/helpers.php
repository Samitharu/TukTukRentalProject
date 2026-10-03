<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Request;

if (! function_exists('asset_v')) {
    /**
     * Resolve a public asset path, preferring the minified `build/` output
     * (produced by `php artisan assets:minify`) when it exists, and always
     * appending a `?v=<filemtime>` cache-busting query string so browsers
     * pick up new deploys without manual cache clearing.
     */
    function asset_v(string $path): string
    {
        $buildPath = preg_replace('/^assets\//', 'assets/build/', $path);
        $buildFullPath = public_path($buildPath);

        $resolvedPath = is_string($buildPath) && is_file($buildFullPath) ? $buildPath : $path;
        $fullPath = public_path($resolvedPath);

        $version = is_file($fullPath) ? (string) filemtime($fullPath) : (string) time();

        return asset($resolvedPath).'?v='.$version;
    }
}

if (! function_exists('csp_nonce')) {
    /**
     * The per-request CSP nonce set by Modules\Core\Http\Middleware\SecurityHeaders,
     * for use in inline <script nonce="{{ csp_nonce() }}"> / <style> tags.
     */
    function csp_nonce(): string
    {
        return (string) (Request::instance()->attributes->get('csp_nonce') ?? '');
    }
}
