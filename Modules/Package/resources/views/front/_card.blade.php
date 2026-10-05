{{-- One package card on the packages and category pages. --}}
<article class="card package-card">
    @if ($package->primaryImage())
        <img src="{{ asset('storage/'.$package->primaryImage()->path) }}" alt="{{ $package->name }}" loading="lazy">
    @endif
    <div class="card__body">
        @if ($package->is_featured)
            <span class="badge">{{ __('core::front.home_featured_packages') }}</span>
        @endif
        <h3 style="font-size:var(--font-size-lg);">{{ $package->name }}</h3>
        <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $package->description), 110) }}</p>
        @if ($package->pricingTiers->isNotEmpty())
            <p>
                <strong>{{ __('core::front.packages_from') }} {{ $package->pricingTiers->first()->price }} {{ config('pricing.default_currency') }}</strong>
                {{ __($package->priceSuffixKey()) }}
            </p>
        @endif
        @include('package::front._package-terms', ['package' => $package])
        <a href="{{ route('packages.show', $package->slugFor(app()->getLocale()) ?? $package->id) }}" class="btn btn--secondary">{{ __('core::front.packages_view_details') }}</a>
    </div>
</article>
