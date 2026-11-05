@php
    $whatsappNumber = config('core.business.whatsapp');
    $whatsappText = rawurlencode(__('core::front.whatsapp_default_message'));
    $siteSettings = \Modules\Core\Models\SiteSetting::current();
    // The Services menu: every visible product line with something to book
    // (an active package) and a page in this language.
    $serviceLines = \Modules\Package\Models\ProductCategory::query()->active()
        ->whereHas('packages', fn ($q) => $q->where('is_active', true))
        ->with('routeSlugs')
        ->orderBy('sort_order')->orderBy('id')->get()
        ->map(fn ($line) => ['name' => $line->name, 'slug' => $line->slugFor(app()->getLocale())])
        ->filter(fn ($line) => $line['slug'] !== null)
        ->values();
    $servicesActive = request()->routeIs('packages.*', 'fleet.*', 'vehicles.*', 'stays.*');
    // Two-line brand: "Happy Journy" / "TukTuk Rental" (or the tagline).
    $brandWords = preg_split('/\s+/', trim((string) config('app.name'))) ?: [];
    $brandTitle = implode(' ', array_slice($brandWords, 0, 2));
    $brandSubtitle = config('core.business.tagline') ?: implode(' ', array_slice($brandWords, 2));
@endphp
{{-- The public design is light-only: the hero photo, dark headline and
     mint backgrounds don't survive the dark-mode tokens. --}}
{{-- SEO: pages may pass canonical, alternates, image, type, schema and
     robots alongside title/description — see <x-core::seo-head>. --}}
<x-core::layouts.master :title="$title ?? config('app.name')" theme="light">
    <x-slot:styles>
        <x-core::seo-head
            :title="$title ?? config('app.name')"
            :description="$description ?? null"
            :canonical="$canonical ?? null"
            :alternates="$alternates ?? null"
            :image="$image ?? null"
            :type="$type ?? 'website'"
            :schema="$schema ?? []"
            :robots="$robots ?? null"
        />
        <link rel="stylesheet" href="{{ asset_v('assets/css/tokens.css') }}" nonce="{{ csp_nonce() }}">
        <link rel="stylesheet" href="{{ asset_v('assets/css/public.css') }}" nonce="{{ csp_nonce() }}">
        {{-- Hold the first paint until the whole <main> has been parsed
             (the footer is the next element). Without it the browser can
             snapshot a half-parsed page for the cross-document view
             transition — header only — and the content pops in after the
             fade, which is what made page changes only *sometimes* smooth.
             The server sends the full HTML at once, so this costs nothing. --}}
        <link rel="expect" href="#site-footer" blocking="render">
        {{ $styles ?? '' }}
    </x-slot:styles>

    <div x-data="{ mobileNavOpen: false }">
        <a href="#main-content" class="skip-link">{{ __('core::front.skip_to_content') }}</a>

        <header class="site-header">
            <div class="container site-header__bar">
                <a href="{{ route('home') }}" class="site-header__brand">
                    @if ($siteSettings->logoUrl())
                        <img src="{{ $siteSettings->logoUrl() }}" alt="{{ config('app.name') }}" class="site-header__logo-img">
                    @else
                        <svg class="site-header__logo-icon" viewBox="0 0 56 36" aria-hidden="true" fill="none">
                            {{-- Gold speed swoosh behind a green tuk tuk --}}
                            <path d="M2 27c5-1.5 9-1.8 13-1.2M5 21.5c3.5-1 6.5-1.2 9.5-.8" stroke="#f4b223" stroke-width="2.4" stroke-linecap="round"/>
                            <path d="M12 28h2a4 4 0 0 0 8 0h14a4 4 0 0 0 8 0h2a2 2 0 0 0 2-2v-5l-4-8a3 3 0 0 0-2.7-1.7H25L20.4 7A4 4 0 0 0 17 5h-3a2 2 0 0 0-2 2v19a2 2 0 0 0 2 2Z" fill="currentColor" opacity="0.18"/>
                            <path d="M12 28h2a4 4 0 0 0 8 0h14a4 4 0 0 0 8 0h2a2 2 0 0 0 2-2v-5l-4-8a3 3 0 0 0-2.7-1.7H25L20.4 7A4 4 0 0 0 17 5h-3a2 2 0 0 0-2 2v19a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
                            <path d="M25 11v9M38 11v9" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <circle cx="22" cy="28" r="3.5" fill="#fff" stroke="currentColor" stroke-width="2.2"/>
                            <circle cx="40" cy="28" r="3.5" fill="#fff" stroke="currentColor" stroke-width="2.2"/>
                        </svg>
                        <span class="site-header__brand-text">
                            <span class="site-header__brand-name">{{ $brandTitle }}</span>
                            @if ($brandSubtitle !== '')
                                <small>{{ $brandSubtitle }}</small>
                            @endif
                        </span>
                    @endif
                </a>

                <nav class="site-nav" aria-label="{{ __('core::front.main_navigation') }}">
                    {{-- Kept short on purpose: Home, Services (dropdown), About Us, FAQ,
                         Contact, then the language switcher and the gold Book Now
                         button. Everything else (blog, pricing…) is in the footer. --}}
                    <ul>
                        <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><span class="site-nav__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 11.5 12 4l8 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10v8.5a1 1 0 0 0 1 1h3v-5h4v5h3a1 1 0 0 0 1-1V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>{{ __('core::front.nav_home') }}</a></li>
                        {{-- Services: one entry per product line (Tuk Tuk, Vehicle Rent,
                             Airport Pickup, Stays, Kite & Surfing). Opens on hover or
                             keyboard focus, and on click/tap via the button. --}}
                        <li class="site-nav__dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                            <button type="button" class="site-nav__trigger" @click="open = !open" :aria-expanded="open" aria-expanded="false" aria-haspopup="true" aria-controls="services-menu" @if($servicesActive) aria-current="page" @endif>
                                <span class="site-nav__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg></span>
                                {{ __('core::front.nav_services') }}
                                <svg class="site-nav__caret" viewBox="0 0 24 24" width="12" height="12" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <ul id="services-menu" class="site-nav__menu">
                                @foreach ($serviceLines as $line)
                                    <li><a href="{{ route('packages.category', $line['slug']) }}">{{ $line['name'] }}</a></li>
                                @endforeach
                                <li><a href="{{ route('packages.index') }}" class="site-nav__menu-all">{{ __('core::front.nav_services_all') }}</a></li>
                            </ul>
                        </li>
                        <li><a href="{{ route('pages.show', 'about-us') }}" @if(request()->is('*/about-us')) aria-current="page" @endif><span class="site-nav__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8.5" r="3.5" stroke="currentColor" stroke-width="1.8"/><path d="M5 19.5c.8-3.4 3.6-5.5 7-5.5s6.2 2.1 7 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>{{ __('core::front.nav_about') }}</a></li>
                        <li><a href="{{ route('faq') }}" @if(request()->routeIs('faq')) aria-current="page" @endif><span class="site-nav__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M9.4 9.4a2.6 2.6 0 1 1 3.9 2.3c-.9.5-1.3 1.1-1.3 2.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="16.6" r="1" fill="currentColor"/></svg></span>{{ __('core::front.nav_faq') }}</a></li>
                        <li><a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif><span class="site-nav__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5.5" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="m3.8 7 8.2 6 8.2-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>{{ __('core::front.nav_contact') }}</a></li>
                    </ul>
                </nav>

                <div class="site-header__actions">
                    <x-localization::switcher :alternates="$alternates ?? null" />
                    <a href="{{ route('booking.start') }}" class="btn btn--primary site-header__book-btn">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" width="18" height="18"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        {{ __('core::front.book_now') }}
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <button type="button" class="nav-toggle" @click="mobileNavOpen = !mobileNavOpen" :aria-expanded="mobileNavOpen" aria-controls="mobile-nav">
                        <span class="visually-hidden">{{ __('core::front.toggle_menu') }}</span>
                        <span aria-hidden="true">&#9776;</span>
                    </button>
                </div>
            </div>

            <nav id="mobile-nav" class="site-nav--mobile" x-show="mobileNavOpen" x-cloak aria-label="{{ __('core::front.main_navigation') }}">
                <ul>
                    <li><a href="{{ route('home') }}">{{ __('core::front.nav_home') }}</a></li>
                    <li>
                        <span class="site-nav--mobile__heading">{{ __('core::front.nav_services') }}</span>
                        <ul class="site-nav--mobile__sub">
                            @foreach ($serviceLines as $line)
                                <li><a href="{{ route('packages.category', $line['slug']) }}">{{ $line['name'] }}</a></li>
                            @endforeach
                            <li><a href="{{ route('packages.index') }}">{{ __('core::front.nav_services_all') }}</a></li>
                        </ul>
                    </li>
                    <li><a href="{{ route('pages.show', 'about-us') }}">{{ __('core::front.nav_about') }}</a></li>
                    <li><a href="{{ route('faq') }}">{{ __('core::front.nav_faq') }}</a></li>
                    <li><a href="{{ route('contact') }}">{{ __('core::front.nav_contact') }}</a></li>
                    <li><a href="{{ route('booking.start') }}" class="btn btn--primary btn--block site-nav__book">{{ __('core::front.book_now') }}</a></li>
                </ul>
            </nav>
        </header>

        <main id="main-content">
            {{ $slot }}
        </main>

        {{--
            Fades in content photos that are still downloading, instead of
            letting them pop in after the page transition has finished.
            Runs before the footer is parsed (the render-blocking target
            above), so images are marked before the first paint; images
            that are already cached are left untouched.
        --}}
        <script nonce="{{ csp_nonce() }}">
            (function () {
                document.querySelectorAll('#main-content img').forEach(function (img) {
                    if (img.complete) return;

                    img.classList.add('img-fade');
                    function reveal() { img.classList.add('is-loaded'); }
                    img.addEventListener('load', reveal, { once: true });
                    img.addEventListener('error', reveal, { once: true });
                });
            })();
        </script>

        <footer id="site-footer" class="site-footer">
            <div class="container site-footer__grid">
                <div>
                    <h3>{{ config('app.name') }}</h3>
                    <p class="text-muted" style="color:var(--color-ink-300);">{{ config('core.business.address') }}</p>
                    <p><a href="tel:{{ config('core.business.phone') }}">{{ config('core.business.phone') }}</a></p>
                    <p><a href="mailto:{{ config('core.business.email') }}">{{ config('core.business.email') }}</a></p>
                </div>
                <div>
                    <h3>{{ __('core::front.footer_explore') }}</h3>
                    <ul>
                        <li><a href="{{ route('fleet.index') }}">{{ __('core::front.nav_fleet') }}</a></li>
                        <li><a href="{{ route('vehicles.index') }}">{{ __('core::front.nav_vehicles') }}</a></li>
                        <li><a href="{{ route('booking.start', ['kind' => 'transfer']) }}">{{ __('core::front.nav_airport') }}</a></li>
                        <li><a href="{{ route('stays.index') }}">{{ __('core::front.nav_stays') }}</a></li>
                        <li><a href="{{ route('packages.index') }}">{{ __('core::front.nav_packages') }}</a></li>
                        <li><a href="{{ route('pricing') }}">{{ __('core::front.pricing_title') }}</a></li>
                        <li><a href="{{ route('pages.show', 'how-it-works') }}">{{ __('core::front.nav_how_it_works') }}</a></li>
                        <li><a href="{{ route('blog.index') }}">{{ __('core::front.nav_blog') }}</a></li>
                        <li><a href="{{ route('faq') }}">{{ __('core::front.nav_faq') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h3>{{ __('core::front.footer_legal') }}</h3>
                    <ul>
                        <li><a href="{{ route('pages.show', 'terms-conditions') }}">{{ __('core::front.nav_terms') }}</a></li>
                        <li><a href="{{ route('pages.show', 'privacy-policy') }}">{{ __('core::front.nav_privacy') }}</a></li>
                        <li><a href="{{ route('pages.show', 'cancellation-policy') }}">{{ __('core::front.nav_cancellation') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h3>{{ __('core::front.footer_contact') }}</h3>
                    <ul>
                        <li><a href="{{ route('contact') }}">{{ __('core::front.nav_contact') }}</a></li>
                        <li><a href="https://wa.me/{{ $whatsappNumber }}">{{ __('core::front.whatsapp') }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="container text-center" style="margin-top:2rem;color:var(--color-ink-300);font-size:var(--font-size-sm);">
                &copy; {{ now()->year }} {{ config('app.name') }}. {{ __('core::front.all_rights_reserved') }}
                <br>
                {{ __('core::front.footer_developed_by', ['company' => 'Saheli Software Solutions']) }}
            </div>
        </footer>

        <a href="https://wa.me/{{ $whatsappNumber }}?text={{ $whatsappText }}" class="whatsapp-float" target="_blank" rel="noopener" aria-label="{{ __('core::front.whatsapp') }}">
            <svg viewBox="0 0 32 32" width="28" height="28" fill="currentColor" aria-hidden="true"><path d="M16.001 3C9.373 3 4 8.373 4 15c0 2.386.697 4.61 1.902 6.48L4 29l7.72-1.867A11.93 11.93 0 0 0 16.001 27C22.63 27 28 21.627 28 15S22.63 3 16.001 3zm0 21.75c-1.98 0-3.822-.57-5.378-1.553l-.385-.236-4.58 1.107 1.15-4.463-.252-.4A9.71 9.71 0 0 1 5.25 15c0-5.93 4.82-10.75 10.75-10.75S26.75 9.07 26.75 15 21.93 24.75 16 24.75zm5.94-7.98c-.326-.163-1.926-.95-2.225-1.058-.298-.108-.515-.163-.732.163-.217.326-.84 1.058-1.03 1.276-.19.217-.38.244-.706.082-.326-.163-1.375-.507-2.62-1.617-.968-.863-1.622-1.93-1.812-2.256-.19-.326-.02-.502.143-.664.146-.146.326-.38.489-.57.163-.19.217-.326.326-.543.109-.217.054-.407-.027-.57-.082-.163-.732-1.766-1.003-2.42-.264-.635-.532-.55-.732-.56-.19-.009-.407-.011-.624-.011-.217 0-.57.082-.868.407-.298.326-1.14 1.113-1.14 2.717 0 1.604 1.167 3.153 1.33 3.37.163.217 2.298 3.51 5.567 4.923.778.336 1.385.536 1.858.686.78.248 1.49.213 2.052.13.626-.093 1.926-.787 2.198-1.548.272-.76.272-1.412.19-1.548-.08-.136-.298-.217-.624-.38z"/></svg>
        </a>

        @if ($siteSettings->tripadvisor_url)
            <a href="{{ $siteSettings->tripadvisor_url }}" class="tripadvisor-float" target="_blank" rel="noopener" aria-label="{{ __('core::front.tripadvisor') }}" title="{{ __('core::front.tripadvisor') }}">
                {{-- Owl eyes, Tripadvisor's mark --}}
                <svg viewBox="0 0 32 32" width="30" height="30" fill="none" aria-hidden="true"><path d="M4 12.5 7 9.5h18l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="17" r="5.5" stroke="currentColor" stroke-width="2"/><circle cx="22" cy="17" r="5.5" stroke="currentColor" stroke-width="2"/><circle cx="10" cy="17" r="2" fill="currentColor"/><circle cx="22" cy="17" r="2" fill="currentColor"/><path d="m14.6 20.6 1.4 2.4 1.4-2.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        @endif

        <div class="mobile-book-bar">
            <a href="{{ route('booking.start') }}" class="btn btn--primary btn--block">{{ __('core::front.book_now') }}</a>
        </div>

        <div id="cookie-banner" class="cookie-banner" style="display:none;" role="dialog" aria-label="{{ __('core::front.cookie_consent_title') }}">
            <p style="margin-bottom:0.75rem;">{{ __('core::front.cookie_consent_text') }}</p>
            <button type="button" id="cookie-consent-accept" class="btn btn--primary" style="min-height:36px;padding:0.4rem 1rem;">
                {{ __('core::front.cookie_consent_accept') }}
            </button>
        </div>
    </div>

    {{--
        Plain vanilla JS, deliberately not Alpine, for this one widget:
        it must work the instant the page loads (no Alpine init-timing
        window to fall through), and "did the user already consent" is a
        yes/no gate, not reactive UI state worth a framework for.
    --}}
    <script nonce="{{ csp_nonce() }}">
        (function () {
            var alreadyConsented = false;
            try { alreadyConsented = localStorage.getItem('cookie_consent') !== null; } catch (e) {}

            if (alreadyConsented) return;

            var banner = document.getElementById('cookie-banner');
            if (!banner) return;

            banner.style.display = 'block';

            document.getElementById('cookie-consent-accept').addEventListener('click', function () {
                try { localStorage.setItem('cookie_consent', 'accepted'); } catch (e) {}
                banner.style.display = 'none';
            });
        })();
    </script>

    {{--
        Generic open/close behaviour shared by every header dropdown — the
        "More" nav menu above and Modules\Localization\...\switcher.blade.php
        (any [data-dropdown-trigger]/[data-dropdown-menu] pair opts in, so a
        future dropdown needs no JS of its own). Plain vanilla JS for the
        same reason as the cookie banner above.
    --}}
    <script nonce="{{ csp_nonce() }}">
        (function () {
            function closeAll() {
                document.querySelectorAll('[data-dropdown-menu]').forEach(function (menu) {
                    menu.hidden = true;
                    var trigger = menu.previousElementSibling;
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                });
            }

            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-dropdown-trigger]');

                if (trigger) {
                    var menu = trigger.nextElementSibling;
                    var wasHidden = menu.hidden;
                    closeAll();
                    if (wasHidden) {
                        menu.hidden = false;
                        trigger.setAttribute('aria-expanded', 'true');
                    }
                    return;
                }

                if (!event.target.closest('.nav-dropdown')) {
                    closeAll();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeAll();
            });
        })();
    </script>

    {{-- The full-screen package photos: any [data-gallery] button opens it (package-gallery.js). --}}
    <dialog class="gallery" data-gallery-dialog aria-label="{{ __('core::front.nav_packages') }}">
        <button type="button" class="gallery__button gallery__close" data-gallery-close aria-label="{{ __('core::front.gallery_close') }}">&times;</button>
        <button type="button" class="gallery__button gallery__previous" data-gallery-previous aria-label="{{ __('core::front.gallery_previous') }}">&lsaquo;</button>
        <figure class="gallery__stage">
            <img data-gallery-image alt="">
            <figcaption data-gallery-caption></figcaption>
        </figure>
        <button type="button" class="gallery__button gallery__next" data-gallery-next aria-label="{{ __('core::front.gallery_next') }}">&rsaquo;</button>
    </dialog>

    <x-slot:scripts>
        <script src="{{ asset_v('assets/vendor/alpine.min.js') }}" defer nonce="{{ csp_nonce() }}"></script>
        <script src="{{ asset_v('assets/js/package-gallery.js') }}" defer nonce="{{ csp_nonce() }}"></script>
        {{ $scripts ?? '' }}
    </x-slot:scripts>
</x-core::layouts.master>
