<x-core::layouts.public :title="__('core::front.packages_title').' · '.config('app.name')" :description="__('core::front.packages_intro')">
    <section class="section">
        <div class="container">
            <h1>{{ __('core::front.packages_title') }}</h1>
            <p class="text-muted">{{ __('core::front.packages_intro') }}</p>

            <div class="grid grid--3">
                @foreach ($packages as $package)
                    <article class="card package-card">
                        @if ($package->primaryImage())
                            <img src="{{ asset('storage/'.$package->primaryImage()->path) }}" alt="{{ $package->name }}" loading="lazy">
                        @endif
                        <div class="card__body">
                            @if ($package->is_featured)
                                <span class="badge">{{ __('core::front.home_featured_packages') }}</span>
                            @endif
                            <h2 style="font-size:var(--font-size-lg);">{{ $package->name }}</h2>
                            <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $package->description), 110) }}</p>
                            @if ($package->pricingTiers->isNotEmpty())
                                <p>
                                    <strong>{{ __('core::front.packages_from') }} {{ $package->pricingTiers->first()->price }} {{ config('pricing.default_currency') }}</strong>
                                    {{ __($package->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day') }}
                                </p>
                            @endif
                            <p class="text-muted" style="font-size:var(--font-size-sm);">{{ __($package->isStay() ? 'core::front.packages_min_nights' : 'core::front.packages_min_days', ['count' => $package->min_days]) }}</p>
                            <a href="{{ route('packages.show', $package->slugFor(app()->getLocale()) ?? $package->id) }}" class="btn btn--secondary">{{ __('core::front.packages_view_details') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-core::layouts.public>
