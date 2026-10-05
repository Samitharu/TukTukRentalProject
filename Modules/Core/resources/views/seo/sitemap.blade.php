{{-- Printed via echo: a literal XML declaration breaks PHP when short_open_tag is on. --}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($groups as $group)
@foreach ($group['urls'] as $code => $url)
    <url>
        <loc>{{ $url }}</loc>
@if ($group['lastmod'])
        <lastmod>{{ $group['lastmod'] }}</lastmod>
@endif
@foreach ($group['urls'] as $altCode => $altUrl)
        <xhtml:link rel="alternate" hreflang="{{ $altCode }}" href="{{ $altUrl }}"/>
@endforeach
@if (isset($group['urls'][$defaultLocale]))
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $group['urls'][$defaultLocale] }}"/>
@endif
    </url>
@endforeach
@endforeach
</urlset>
