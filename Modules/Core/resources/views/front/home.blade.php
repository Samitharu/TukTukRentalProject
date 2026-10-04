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

                <div class="package-feature-grid">
                @foreach ($featuredPackages as $package)
                    @php $packageImages = $package->images->take(2); @endphp
                    <article class="package-feature">
                        @if ($packageImages->isEmpty())
                            <div class="package-feature__icon" aria-hidden="true">&#127965;&#65039;</div>
                        @endif
                        <div class="package-feature__body">
                            <h3>{{ $package->name }}</h3>
                            <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $package->description), 110) }}</p>
                            <div class="package-feature__meta">
                                <span class="package-feature__price">
                                    {{ __('core::front.packages_from') }}
                                    <strong>{{ optional($package->pricingTiers->first())->price }} {{ config('pricing.default_currency') }}</strong>
                                    {{ __($package->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day') }}
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

    @if ($customerReviews->isNotEmpty())
        @php $averageRating = round($customerReviews->avg('rating'), 1); @endphp
        <section class="section reviews-showcase" aria-labelledby="reviews-showcase-title">
            <div class="container">
                <div class="reviews-showcase__head">
                    <div>
                        <p class="reviews-showcase__eyebrow">{{ __('core::front.home_reviews_eyebrow') }}</p>
                        <h2 id="reviews-showcase-title">{{ __('core::front.home_testimonials') }}</h2>
                        <p class="reviews-showcase__summary">
                            <span class="reviews-showcase__score">{{ number_format($averageRating, 1) }}</span>
                            <span class="review-stars" aria-hidden="true">
                                @for ($i = 1; $i <= 5; $i++)
                                    <svg viewBox="0 0 24 24" class="{{ $i <= round($averageRating) ? 'is-filled' : '' }}"><path d="M12 2.8l2.85 5.8 6.4.93-4.63 4.5 1.1 6.37L12 17.4l-5.72 3 1.1-6.37-4.63-4.5 6.4-.93L12 2.8Z"/></svg>
                                @endfor
                            </span>
                            <span class="reviews-showcase__count">{{ trans_choice('core::front.home_reviews_count', $customerReviews->count(), ['count' => $customerReviews->count(), 'rating' => number_format($averageRating, 1)]) }}</span>
                        </p>
                    </div>

                    <div class="reviews-showcase__nav" data-review-nav hidden>
                        <button type="button" class="reviews-showcase__arrow" data-review-prev aria-label="{{ __('core::front.home_reviews_prev') }}">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <button type="button" class="reviews-showcase__arrow" data-review-next aria-label="{{ __('core::front.home_reviews_next') }}">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </div>

                <div class="reviews-carousel" data-review-carousel role="region" aria-roledescription="carousel" aria-label="{{ __('core::front.home_testimonials') }}">
                    <ul class="reviews-carousel__track" data-review-track tabindex="0">
                        @foreach ($customerReviews as $review)
                            <li class="reviews-carousel__slide" aria-roledescription="slide" aria-label="{{ __('core::front.home_reviews_slide', ['current' => $loop->iteration, 'total' => $loop->count]) }}">
                                <figure class="review-slide">
                                    <svg class="review-slide__quote" viewBox="0 0 48 48" aria-hidden="true"><path d="M20 12c-7 2.5-12 8.6-12 16.2V36h12V24h-6c.4-4.4 3.3-7.6 7.6-9L20 12Zm20 0c-7 2.5-12 8.6-12 16.2V36h12V24h-6c.4-4.4 3.3-7.6 7.6-9L40 12Z" fill="currentColor"/></svg>

                                    <div class="review-stars" role="img" aria-label="{{ trans_choice('core::front.review_star', $review['rating'], ['count' => $review['rating']]) }}">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <svg viewBox="0 0 24 24" aria-hidden="true" class="{{ $i <= $review['rating'] ? 'is-filled' : '' }}"><path d="M12 2.8l2.85 5.8 6.4.93-4.63 4.5 1.1 6.37L12 17.4l-5.72 3 1.1-6.37-4.63-4.5 6.4-.93L12 2.8Z"/></svg>
                                        @endfor
                                    </div>

                                    <blockquote class="review-slide__text">
                                        <p>{{ $review['content'] }}</p>
                                    </blockquote>

                                    <figcaption class="review-slide__author">
                                        <span class="review-slide__avatar" aria-hidden="true">{{ $review['initials'] }}</span>
                                        <span class="review-slide__who">
                                            <strong>{{ $review['name'] }}</strong>
                                            <span>
                                                @if ($review['country']){{ $review['country'] }}@endif
                                                @if ($review['country'] && $review['date']) &middot; @endif
                                                @if ($review['date'])<time datetime="{{ $review['date']->toDateString() }}">{{ $review['date']->translatedFormat('M Y') }}</time>@endif
                                            </span>
                                        </span>
                                        @if ($review['verified'])
                                            <span class="review-slide__verified" title="{{ __('core::front.home_reviews_verified') }}">
                                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l2.2 1.6 2.7-.1.9 2.6 2.2 1.6-.8 2.6.8 2.6-2.2 1.6-.9 2.6-2.7-.1L12 21l-2.2-1.6-2.7.1-.9-2.6-2.2-1.6.8-2.6-.8-2.6 2.2-1.6.9-2.6 2.7.1L12 3Z" fill="currentColor"/><path d="m8.6 12.2 2.2 2.2 4.6-4.6" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                <span>{{ __('core::front.home_reviews_verified') }}</span>
                                            </span>
                                        @endif
                                    </figcaption>
                                </figure>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="reviews-carousel__dots" data-review-dots data-dot-label="{{ __('core::front.home_reviews_goto') }}" role="group" aria-label="{{ __('core::front.home_testimonials') }}" hidden></div>
            </div>
        </section>

        {{--
            Plain vanilla JS (same reasoning as the layout's cookie banner):
            native scroll-snap does the sliding, so touch swipe and keyboard
            scrolling work even before/without this script; it only adds the
            arrows, dots and a gentle autoplay that pauses on hover/focus and
            is skipped entirely for prefers-reduced-motion.
        --}}
        <script nonce="{{ csp_nonce() }}">
            (function () {
                var carousel = document.querySelector('[data-review-carousel]');
                if (!carousel) return;

                var section = carousel.closest('section');
                var track = carousel.querySelector('[data-review-track]');
                var slides = Array.prototype.slice.call(track.children);
                var nav = section.querySelector('[data-review-nav]');
                var dotsWrap = section.querySelector('[data-review-dots]');
                var dotLabel = dotsWrap.getAttribute('data-dot-label') || '';
                var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                var AUTOPLAY_MS = 6000;
                var timer = null;
                var paused = false;
                var dots = [];

                function step() {
                    return slides.length > 1 ? slides[1].offsetLeft - slides[0].offsetLeft : track.clientWidth;
                }

                function maxIndex() {
                    var perView = Math.max(1, Math.round(track.clientWidth / step()));
                    return Math.max(0, slides.length - perView);
                }

                function currentIndex() {
                    return Math.min(maxIndex(), Math.round(track.scrollLeft / step()));
                }

                function goTo(index) {
                    var max = maxIndex();
                    if (index > max) index = 0;
                    if (index < 0) index = max;

                    track.scrollTo({
                        left: slides[index].offsetLeft - slides[0].offsetLeft,
                        behavior: reduceMotion ? 'auto' : 'smooth'
                    });
                }

                function updateDots() {
                    var active = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2 ? dots.length - 1 : currentIndex();
                    dots.forEach(function (dot, i) {
                        dot.classList.toggle('is-active', i === active);
                        dot.setAttribute('aria-current', i === active ? 'true' : 'false');
                    });
                }

                function build() {
                    var max = maxIndex();
                    nav.hidden = max === 0;
                    dotsWrap.hidden = max === 0;
                    dotsWrap.innerHTML = '';
                    dots = [];

                    for (var i = 0; i <= max && max > 0; i++) {
                        var dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'reviews-carousel__dot';
                        dot.setAttribute('aria-label', dotLabel.replace(':number', String(i + 1)));
                        dot.addEventListener('click', goTo.bind(null, i));
                        dotsWrap.appendChild(dot);
                        dots.push(dot);
                    }

                    updateDots();
                }

                function stop() {
                    if (timer) clearInterval(timer);
                    timer = null;
                }

                function start() {
                    stop();
                    if (reduceMotion || paused || maxIndex() === 0) return;
                    timer = setInterval(function () { goTo(currentIndex() + 1); }, AUTOPLAY_MS);
                }

                function navigate(delta) {
                    goTo(currentIndex() + delta);
                    start();
                }

                section.querySelector('[data-review-prev]').addEventListener('click', function () { navigate(-1); });
                section.querySelector('[data-review-next]').addEventListener('click', function () { navigate(1); });

                var ticking = false;
                track.addEventListener('scroll', function () {
                    if (ticking) return;
                    ticking = true;
                    requestAnimationFrame(function () { ticking = false; updateDots(); });
                }, { passive: true });

                function pause() { paused = true; stop(); }
                function resume() { paused = false; start(); }

                section.addEventListener('mouseenter', pause);
                section.addEventListener('mouseleave', resume);
                section.addEventListener('focusin', pause);
                section.addEventListener('focusout', function (event) {
                    if (!section.contains(event.relatedTarget)) resume();
                });
                track.addEventListener('pointerdown', pause);
                document.addEventListener('visibilitychange', function () {
                    document.hidden ? stop() : start();
                });

                var resizeTimer = null;
                window.addEventListener('resize', function () {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function () { build(); start(); }, 150);
                });

                build();
                start();
            })();
        </script>
    @endif

    <section id="feedback" class="section feedback-section">
        <div class="container feedback-section__layout">
            <div class="feedback-section__intro">
                <p class="feedback-section__eyebrow">{{ __('core::front.review_home_eyebrow') }}</p>
                <h2>{{ __('core::front.review_title') }}</h2>
                <p>{{ __('core::front.review_intro') }}</p>
                <div class="feedback-section__stars" aria-hidden="true">★★★★★</div>
            </div>

            @if (session('review_submitted'))
                <div class="feedback-section__success" role="status">
                    <span class="review-card__success-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <p>{{ __('core::front.review_thanks') }}</p>
                </div>
            @else
                <form method="POST" action="{{ route('booking.feedback.home.store') }}" class="review-form feedback-form">
                    @csrf
                    <div class="field">
                        <label for="feedback-reference">{{ __('core::front.review_booking_reference') }}</label>
                        <input type="text" id="feedback-reference" name="reference" value="{{ old('reference') }}" placeholder="MTR-ABCDEFGH" maxlength="20" autocomplete="off" autocapitalize="characters" required>
                        <small>{{ __('core::front.review_home_reference_hint') }}</small>
                        @error('reference')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <fieldset class="field star-rating">
                        <legend>{{ __('core::front.review_rating_label') }}</legend>
                        <div class="star-rating__stars">
                            @for ($i = 5; $i >= 1; $i--)
                                <input type="radio" id="home-rating-{{ $i }}" name="rating" value="{{ $i }}" class="visually-hidden" @checked((int) old('rating') === $i) required>
                                <label for="home-rating-{{ $i }}" title="{{ trans_choice('core::front.review_star', $i, ['count' => $i]) }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.85 5.8 6.4.93-4.63 4.5 1.1 6.37L12 17.4l-5.72 3 1.1-6.37-4.63-4.5 6.4-.93L12 2.8Z"/></svg>
                                    <span class="visually-hidden">{{ trans_choice('core::front.review_star', $i, ['count' => $i]) }}</span>
                                </label>
                            @endfor
                        </div>
                        @error('rating')<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="field review-form__comment">
                        <label for="feedback-comment">{{ __('core::front.review_comment_label') }}</label>
                        <textarea id="feedback-comment" name="comment" maxlength="500" rows="4" placeholder="{{ __('core::front.review_comment_placeholder') }}">{{ old('comment') }}</textarea>
                        @error('comment')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="btn btn--primary feedback-form__submit">{{ __('core::front.review_submit') }}</button>
                </form>
            @endif
        </div>
    </section>
</x-core::layouts.public>
