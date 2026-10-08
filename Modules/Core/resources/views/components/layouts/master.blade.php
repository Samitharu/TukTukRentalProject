<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if (! empty($theme ?? null)) data-theme="{{ $theme }}" style="color-scheme: {{ $theme }};" @endif>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>{{ $title ?? config('app.name') }}</title>

    {{-- Browser tab icon: the logo uploaded under Branding (public/favicon.ico is empty). --}}
    @if ($faviconUrl = \Modules\Core\Models\SiteSetting::current()->logoUrl())
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif

    @if (! empty($description ?? null))
        <meta name="description" content="{{ $description }}">
    @endif

    {{-- Design-system stylesheets land here in Phase 5 (public site) / are set per-layout for the admin panel. No Vite/Node build step is used anywhere in this project — see docs/01-architecture.md. --}}
    {{ $styles ?? '' }}
</head>

<body>
    {{ $slot }}

    {{ $scripts ?? '' }}
</body>

</html>
