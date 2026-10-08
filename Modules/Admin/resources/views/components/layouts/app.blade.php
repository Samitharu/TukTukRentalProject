<x-core::layouts.master :title="($title ?? __('Dashboard')).' · '.config('app.name').' Admin'">
    <x-slot:styles>
        <link rel="stylesheet" href="{{ asset_v('assets/css/admin.css') }}" nonce="{{ csp_nonce() }}">
    </x-slot:styles>

    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a href="{{ route('admin.dashboard') }}" class="admin-sidebar__brand">
                @php $logoUrl = \Modules\Core\Models\SiteSetting::current()->logoUrl(); @endphp
                @if ($logoUrl)
                    <span class="admin-sidebar__brand-mark admin-sidebar__brand-mark--logo" aria-hidden="true"><img src="{{ $logoUrl }}" alt=""></span>
                @else
                    <span class="admin-sidebar__brand-mark" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span>
                @endif
                <span class="admin-sidebar__brand-copy">
                    <strong>{{ config('app.name') }}</strong>
                    <small>{{ __('Admin workspace') }}</small>
                </span>
            </a>
            <p class="admin-sidebar__label">{{ __('Workspace') }}</p>
            <ul class="admin-nav">
                <li><a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>{{ __('Dashboard') }}</a></li>
                @can('viewAny', \Modules\Booking\Models\Booking::class)
                    <li><a href="{{ route('admin.bookings.index') }}" @if(request()->routeIs('admin.bookings.*')) aria-current="page" @endif>{{ __('Bookings') }}</a></li>
                @endcan
                @can('viewAny', \Modules\Customer\Models\Customer::class)
                    <li><a href="{{ route('admin.customers.index') }}" @if(request()->routeIs('admin.customers.*')) aria-current="page" @endif>{{ __('Customers') }}</a></li>
                @endcan
                @can('viewAny', \Modules\Localization\Models\Locale::class)
                    <li><a href="{{ route('admin.locales.index') }}" @if(request()->routeIs('admin.locales.*')) aria-current="page" @endif>{{ __('Locales') }}</a></li>
                @endcan
                @can('viewAny', \App\Models\User::class)
                    <li><a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>{{ __('Users') }}</a></li>
                @endcan
                @can('viewAny', \Modules\Fleet\Models\Vehicle::class)
                    <li><a href="{{ route('admin.fleet.vehicles.index') }}" @if(request()->routeIs('admin.fleet.vehicles.*')) aria-current="page" @endif>{{ __('Tuk Tuks & Stays') }}</a></li>
                    <li><a href="{{ route('admin.fleet.categories.index') }}" @if(request()->routeIs('admin.fleet.categories.*')) aria-current="page" @endif>{{ __('Unit Types') }}</a></li>
                @endcan
                @can('viewAny', \Modules\Package\Models\Package::class)
                    <li><a href="{{ route('admin.package-categories.index') }}" @if(request()->routeIs('admin.package-categories.*')) aria-current="page" @endif>{{ __('Package Categories') }}</a></li>
                    <li><a href="{{ route('admin.packages.index') }}" @if(request()->routeIs('admin.packages.*')) aria-current="page" @endif>{{ __('Packages') }}</a></li>
                    <li><a href="{{ route('admin.addons.index') }}" @if(request()->routeIs('admin.addons.*')) aria-current="page" @endif>{{ __('Add-ons') }}</a></li>
                @endcan
                @can('pricing.manage')
                    <li><a href="{{ route('admin.coupons.index') }}" @if(request()->routeIs('admin.coupons.*')) aria-current="page" @endif>{{ __('Coupons') }}</a></li>
                    <li><a href="{{ route('admin.currencies.index') }}" @if(request()->routeIs('admin.currencies.*')) aria-current="page" @endif>{{ __('Currencies') }}</a></li>
                @endcan
                @can('viewAny', \Modules\Availability\Models\BusinessLocation::class)
                    <li><a href="{{ route('admin.locations.index') }}" @if(request()->routeIs('admin.locations.*')) aria-current="page" @endif>{{ __('Locations') }}</a></li>
                    <li><a href="{{ route('admin.blackouts.index') }}" @if(request()->routeIs('admin.blackouts.*')) aria-current="page" @endif>{{ __('Blackout Dates') }}</a></li>
                @endcan
                @can('cms.view')
                    <li><a href="{{ route('admin.pages.index') }}" @if(request()->routeIs('admin.pages.*')) aria-current="page" @endif>{{ __('Pages') }}</a></li>
                    <li><a href="{{ route('admin.faqs.index') }}" @if(request()->routeIs('admin.faqs.*')) aria-current="page" @endif>{{ __('FAQs') }}</a></li>
                    <li><a href="{{ route('admin.blog.index') }}" @if(request()->routeIs('admin.blog.*')) aria-current="page" @endif>{{ __('Blog') }}</a></li>
                    <li><a href="{{ route('admin.testimonials.index') }}" @if(request()->routeIs('admin.testimonials.*')) aria-current="page" @endif>{{ __('Testimonials') }}</a></li>
                    <li><a href="{{ route('admin.reviews.index') }}" @if(request()->routeIs('admin.reviews.*')) aria-current="page" @endif>{{ __('Reviews') }}</a></li>
                @endcan
                @can('settings.manage')
                    <li><a href="{{ route('admin.branding.edit') }}" @if(request()->routeIs('admin.branding.*')) aria-current="page" @endif>{{ __('Branding') }}</a></li>
                @endcan
            </ul>
            <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="admin-sidebar__website">
                <span>{{ __('View website') }}</span>
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M11.5 3.5h5v5M16.25 3.75 9 11M15.5 11v4.5a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <h1>{{ $title ?? __('Dashboard') }}</h1>
                <div class="admin-topbar__actions">
                    <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="admin-topbar__site-link">{{ __('View website') }}</a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Sign out') }}</button>
                    </form>
                </div>
            </div>

            @if (session('status'))
                <div class="admin-alert admin-alert--success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="admin-alert admin-alert--error">
                    <ul style="margin:0;padding-left:1.1rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-core::layouts.master>
