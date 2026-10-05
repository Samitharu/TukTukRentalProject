{{-- Category pills at the top of the packages pages; $current is the open category, or null for "All". --}}
@if ($tabs->count() > 1)
    <nav class="category-tabs" aria-label="{{ __('core::front.nav_packages') }}">
        <a href="{{ route('packages.index') }}" class="category-tabs__tab" @if ($current === null) aria-current="page" @endif>{{ __('core::front.packages_all') }}</a>
        @foreach ($tabs as $tab)
            @php $tabSlug = $tab->slugFor(app()->getLocale()); @endphp
            @if ($tabSlug !== null)
                <a href="{{ route('packages.category', $tabSlug) }}" class="category-tabs__tab" @if ($current?->is($tab)) aria-current="page" @endif>{{ $tab->name }}</a>
            @endif
        @endforeach
    </nav>
@endif
