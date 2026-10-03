@php
    $siteSettings = \Modules\Core\Models\SiteSetting::current();
    $trustBadges = [
        ['label' => __('core::front.trust_insured'), 'icon' => 'shield'],
        ['label' => __('core::front.trust_support'), 'icon' => 'headset'],
        ['label' => __('core::front.trust_reviews'), 'icon' => 'people'],
        ['label' => __('core::front.trust_cancellation'), 'icon' => 'calendar'],
    ];

    // Highlights the first "tuk tuk" in the hero title with the accent
    // colour + hand-drawn underline, same as the reference design — only
    // kicks in when that literal phrase is present, so locales whose
    // translation doesn't contain it just render the plain heading.
    $heroTitle = __('core::front.home_hero_title');
    $heroTitleHtml = preg_replace(
        '/(tuk\s?tuk)/i',
        '<span class="hero__highlight">$1<svg class="hero__highlight-underline" viewBox="0 0 160 20" preserveAspectRatio="none" aria-hidden="true"><path d="M4 14c30-10 120-10 152 0" stroke="currentColor" stroke-width="6" stroke-linecap="round" fill="none"/></svg></span>',
        $heroTitle,
        1
    );
@endphp
<x-core::layouts.public :title="config('app.name')" :description="__('core::front.home_hero_lead')">
    {{-- Falls back to the bundled photo until a hero image is uploaded in
         the control panel. The uploaded image should be a photo without
         text: the headline and badges are rendered as real HTML on top. --}}
    <section class="hero">
        <img src="{{ $siteSettings->heroImageUrl() ?? asset('assets/images/hero-tuktuk.jpg') }}" alt="" class="hero__photo" loading="eager" fetchpriority="high">
        <div class="container">
            <div class="hero__content">
                <h1 class="hero__title">{!! $heroTitleHtml !!}</h1>
                <p class="hero__lead">{{ __('core::front.home_hero_lead') }}</p>

                <div class="hero__actions">
                    <a href="{{ route('booking.start') }}" class="btn btn--primary">{{ __('core::front.book_now') }}</a>
                    <a href="{{ route('fleet.index') }}" class="btn btn--secondary hero__secondary">{{ __('core::front.nav_fleet') }}</a>
                </div>

                <div class="hero__badges">
                    @foreach ($trustBadges as $badge)
                        <div class="hero__badge">
                            <span class="hero__badge-icon">
                                @switch($badge['icon'])
                                    @case('shield')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @break
                                    @case('headset')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 13v-1a8 8 0 0 1 16 0v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="3" y="13" width="4" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="17" y="13" width="4" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg>
                                        @break
                                    @case('people')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3 2.7-5 6-5s6 2 6 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="9" r="2.3" stroke="currentColor" stroke-width="1.8"/><path d="M15.5 20c.2-2.2 1.8-3.8 3.9-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        @break
                                    @case('calendar')
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                @endswitch
                            </span>
                            <span>{{ $badge['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($featuredPackages->isNotEmpty())
        <section class="section packages-section">
            <div class="container">
                <div class="packages-section__head">
                    <h2 class="packages-section__title">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" width="26" height="26"><path d="M4 15c4-7 11-9 16-9-1 5-4 11-11 11-2 0-4-.7-5-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4 20c2-3 4.5-5 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        {{ __('core::front.home_featured_packages') }}
                    </h2>
                    <a href="{{ route('packages.index') }}" class="btn btn--primary packages-section__all-link">
                        {{ __('core::front.home_view_all_packages') }}
                        <svg aria-hidden="true" width="12" height="12" viewBox="0 0 10 10" fill="none"><path d="M3 1l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </div>

                @foreach ($featuredPackages as $package)
                    @php $packageImages = $package->images->take(2); @endphp
                    <article class="package-feature">
                        <div class="package-feature__icon" aria-hidden="true">&#127965;&#65039;</div>
                        <div class="package-feature__body">
                            <h3>{{ $package->name }}</h3>
                            <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $package->description), 110) }}</p>
                            <div class="package-feature__meta">
                                <span class="package-feature__price">
                                    {{ __('core::front.packages_from') }}
                                    <strong>{{ optional($package->pricingTiers->first())->price }} {{ config('pricing.default_currency') }}</strong>
                                    {{ __('core::front.packages_per_day') }}
                                </span>
                                <a href="{{ route('packages.show', $package->slugFor(app()->getLocale()) ?? $package->id) }}" class="btn btn--secondary">
                                    {{ __('core::front.packages_view_details') }}
                                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 10 10" fill="none"><path d="M3 1l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </div>
                        </div>
                        @if ($packageImages->isNotEmpty())
                            <div class="package-feature__photos">
                                @foreach ($packageImages as $image)
                                    <img src="{{ $image->url() }}" alt="" loading="lazy" class="package-feature__photo">
                                @endforeach
                                <span class="package-feature__explore">{{ __('core::front.packages_explore_more') }}</span>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div class="trust-strip">
        <span class="trust-strip__item">
            <span class="trust-strip__icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            {{ __('core::front.trust_insured') }}
        </span>
        <span class="trust-strip__item">
            <span class="trust-strip__icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 13v-1a8 8 0 0 1 16 0v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="3" y="13" width="4" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="17" y="13" width="4" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg></span>
            {{ __('core::front.trust_support') }}
        </span>
        <span class="trust-strip__item">
            <span class="trust-strip__icon trust-strip__icon--accent"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/></svg></span>
            {{ __('core::front.trust_reviews') }}
        </span>
        <span class="trust-strip__item">
            <span class="trust-strip__icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            {{ __('core::front.trust_cancellation') }}
        </span>
    </div>

    <section class="section section--muted">
        <div class="container">
            <h2>{{ __('core::front.home_why_us') }}</h2>
            <div class="grid grid--3">
                <div>
                    <h3>{{ __('core::front.home_why_transparent_title') }}</h3>
                    <p class="text-muted">{{ __('core::front.home_why_transparent_body') }}</p>
                </div>
                <div>
                    <h3>{{ __('core::front.home_why_maintained_title') }}</h3>
                    <p class="text-muted">{{ __('core::front.home_why_maintained_body') }}</p>
                </div>
                <div>
                    <h3>{{ __('core::front.home_why_support_title') }}</h3>
                    <p class="text-muted">{{ __('core::front.home_why_support_body') }}</p>
                </div>
            </div>
        </div>
    </section>

    @if ($vehicles->isNotEmpty())
        <section class="section">
            <div class="container">
                <h2>{{ __('core::front.home_fleet_teaser') }}</h2>
                <p class="text-muted">{{ __('core::front.home_fleet_teaser_body') }}</p>
                <div class="grid grid--4">
                    @foreach ($vehicles as $vehicle)
                        <article class="card fleet-card">
                            @if ($vehicle->primaryImage())
                                <img src="{{ asset('storage/'.$vehicle->primaryImage()->path) }}" alt="{{ $vehicle->name }}" loading="lazy">
                            @endif
                            <div class="card__body">
                                <h3 style="font-size:var(--font-size-base);">{{ $vehicle->name }}</h3>
                                <p class="text-muted">{{ __('core::front.fleet_seats', ['count' => $vehicle->seats]) }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="text-center" style="margin-top:2rem;"><a href="{{ route('fleet.index') }}" class="btn btn--secondary">{{ __('core::front.nav_fleet') }}</a></p>
            </div>
        </section>
    @endif

    @if ($testimonials->isNotEmpty())
        <section class="section section--muted">
            <div class="container">
                <h2>{{ __('core::front.home_testimonials') }}</h2>
                <div class="grid grid--3">
                    @foreach ($testimonials as $testimonial)
                        <blockquote class="card" style="margin:0;">
                            <div class="card__body">
                                <p>&ldquo;{{ $testimonial->content }}&rdquo;</p>
                                <footer class="text-muted">{{ $testimonial->customer_name }}, {{ $testimonial->country }} &mdash; {{ str_repeat('★', $testimonial->rating) }}</footer>
                            </div>
                        </blockquote>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-core::layouts.public>
