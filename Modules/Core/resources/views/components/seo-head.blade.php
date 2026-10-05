{{--
    Search-engine and social-share tags for every public page (rendered
    into <head> by the public layout). Pages only pass what is specific to
    them; everything else has a sensible default:

      canonical   — current URL minus filter/tracking query strings
      alternates  — locale => URL; defaults to swapping the /{locale}
                    segment (pages with translated slugs pass their own)
      image       — page photo, else the site's hero image
      type        — og:type ("website", "article", "product")
      schema      — list of schema.org arrays, printed as JSON-LD
      robots      — "noindex, nofollow" is forced on routes behind the
                    `noindex` middleware (booking flow, control panel)
--}}
@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'alternates' => null,
    'image' => null,
    'type' => 'website',
    'schema' => [],
    'robots' => null,
])
@php
    use Modules\Core\Http\Middleware\NoIndex;
    use Modules\Core\Support\Seo;

    $noindex = request()->attributes->get(NoIndex::ATTRIBUTE) === true;
    $robots = $noindex ? 'noindex, nofollow' : ($robots ?? 'index, follow, max-image-preview:large');
    $metaDescription = Seo::description($description) ?: Seo::description(__('core::front.home_hero_lead'));
    $canonical ??= Seo::canonicalUrl();
    $alternates = $noindex ? [] : ($alternates ?? Seo::alternatesForCurrentPath());
    $defaultCode = Seo::defaultLocaleCode();
    $image ??= Seo::defaultImage();
    $currentCode = app()->getLocale();
@endphp
<meta name="robots" content="{{ $robots }}">
<meta name="description" content="{{ $metaDescription }}">
@unless ($noindex)
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternates as $code => $href)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
    @endforeach
    @if (isset($alternates[$defaultCode]))
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[$defaultCode] }}">
    @endif
@endunless

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="{{ Seo::ogLocale($currentCode) }}">
@foreach (array_keys($alternates) as $code)
    @if ($code !== $currentCode)
        <meta property="og:locale:alternate" content="{{ Seo::ogLocale($code) }}">
    @endif
@endforeach
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $image }}">

@unless ($noindex)
    @foreach (array_filter($schema) as $item)
        <script type="application/ld+json" nonce="{{ csp_nonce() }}">{!! Seo::jsonLd($item) !!}</script>
    @endforeach
@endunless
