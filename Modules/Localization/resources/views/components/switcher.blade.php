{{-- $alternates (locale => URL), when the page provides it, gives the
     right translated slug per language; otherwise only the /{locale}
     segment is swapped, which is correct for pages without a slug. --}}
@props(['alternates' => null])
@php
    $segments = explode('/', trim(request()->path(), '/'));
    $currentCode = app()->getLocale();
    $locales = \Modules\Localization\Models\Locale::activeCached();
    $current = $locales->firstWhere('code', $currentCode);
@endphp
{{--
    The open/close behaviour (not the visual chrome) is handled by the
    generic [data-dropdown*] script in
    Modules\Core\resources\views\components\layouts\public.blade.php, shared
    with the header's "More" nav dropdown — plain vanilla JS, not Alpine, for
    the same reason as the cookie-consent banner there: this needs to work
    the instant the page loads, with no framework-init timing window.
--}}
<div class="locale-switcher nav-dropdown">
    <button
        type="button"
        class="locale-switcher__trigger"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-label="{{ __('localization::switcher.aria_label') }}"
        data-dropdown-trigger
    >
        @if ($current?->flag_icon)
            <img src="{{ asset_v('assets/icons/flags/'.$current->flag_icon.'.svg') }}" width="20" height="15" alt="" aria-hidden="true" class="locale-switcher__flag">
        @endif
        <span class="locale-switcher__label">{{ strtoupper($currentCode) }}</span>
        <svg aria-hidden="true" width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <ul class="nav-dropdown__menu locale-switcher__menu" role="listbox" aria-label="{{ __('localization::switcher.aria_label') }}" hidden data-dropdown-menu>
        @foreach ($locales as $locale)
            @php
                $targetSegments = $segments;
                $targetSegments[0] = $locale->code;
                $targetPath = '/'.implode('/', $targetSegments);
                $query = request()->getQueryString();
                $href = $targetPath.($query ? '?'.$query : '');

                if (is_array($alternates)) {
                    // A record with no slug in that language: its home page
                    // rather than a URL that would 404.
                    $href = $alternates[$locale->code] ?? route('home', ['locale' => $locale->code]);
                }
            @endphp
            <li role="option" aria-selected="{{ $locale->code === $currentCode ? 'true' : 'false' }}">
                <a href="{{ $href }}" hreflang="{{ $locale->code }}" {{ $locale->code === $currentCode ? 'aria-current=true' : '' }}>
                    @if ($locale->flag_icon)
                        <img src="{{ asset_v('assets/icons/flags/'.$locale->flag_icon.'.svg') }}" width="20" height="15" alt="" aria-hidden="true" class="locale-switcher__flag">
                    @endif
                    <span>{{ $locale->native_name }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
