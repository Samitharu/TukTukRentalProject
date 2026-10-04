<x-admin::layouts.app :title="__('Dashboard')">
    <section class="admin-welcome">
        <div>
            <p class="admin-eyebrow">{{ __('Admin workspace') }}</p>
            <h2>{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</h2>
            <p>{{ __('Choose an area below to manage your rental business.') }}</p>
        </div>
        <time datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
    </section>

    <section class="admin-section" aria-labelledby="admin-quick-access-title">
        <div class="admin-section__heading">
            <div>
                <h2 id="admin-quick-access-title">{{ __('Quick access') }}</h2>
                <p>{{ __('Go straight to the tools you use most.') }}</p>
            </div>
        </div>

        <div class="admin-quick-links">
            @can('viewAny', \Modules\Booking\Models\Booking::class)
                <a href="{{ route('admin.bookings.index') }}" class="admin-quick-link">
                    <span class="admin-quick-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 3v4m8-4v4M4 10h16m-11 4h2m3 0h2m-7 3h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <span class="admin-quick-link__copy"><strong>{{ __('Bookings') }}</strong><small>{{ __('Review and manage reservations') }}</small></span>
                    <span class="admin-quick-link__arrow" aria-hidden="true">&#8599;</span>
                </a>
            @endcan
            @can('viewAny', \Modules\Customer\Models\Customer::class)
                <a href="{{ route('admin.customers.index') }}" class="admin-quick-link">
                    <span class="admin-quick-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0m1-13.5a3 3 0 0 1 0 5.8m1 2.4a5.5 5.5 0 0 1 4 5.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <span class="admin-quick-link__copy"><strong>{{ __('Customers') }}</strong><small>{{ __('Browse customer records') }}</small></span>
                    <span class="admin-quick-link__arrow" aria-hidden="true">&#8599;</span>
                </a>
            @endcan
            @can('viewAny', \Modules\Fleet\Models\Vehicle::class)
                <a href="{{ route('admin.fleet.vehicles.index') }}" class="admin-quick-link">
                    <span class="admin-quick-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m5 11 1.6-4.2A2 2 0 0 1 8.5 5.5h7a2 2 0 0 1 1.9 1.3L19 11m-15 0h16a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-1m-14 0H5a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1Zm2 7h10M7 14h.01M17 14h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="7" cy="18" r="1.5" fill="currentColor"/><circle cx="17" cy="18" r="1.5" fill="currentColor"/></svg></span>
                    <span class="admin-quick-link__copy"><strong>{{ __('Fleet') }}</strong><small>{{ __('Manage vehicles and availability') }}</small></span>
                    <span class="admin-quick-link__arrow" aria-hidden="true">&#8599;</span>
                </a>
            @endcan
            @can('viewAny', \Modules\Package\Models\Package::class)
                <a href="{{ route('admin.packages.index') }}" class="admin-quick-link">
                    <span class="admin-quick-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12v8.5M8 5.3l8 4.5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></span>
                    <span class="admin-quick-link__copy"><strong>{{ __('Packages') }}</strong><small>{{ __('Update rental packages and add-ons') }}</small></span>
                    <span class="admin-quick-link__arrow" aria-hidden="true">&#8599;</span>
                </a>
            @endcan
            @can('viewAny', \App\Models\User::class)
                <a href="{{ route('admin.users.index') }}" class="admin-quick-link">
                    <span class="admin-quick-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.7"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <span class="admin-quick-link__copy"><strong>{{ __('Users') }}</strong><small>{{ __('Manage admin access') }}</small></span>
                    <span class="admin-quick-link__arrow" aria-hidden="true">&#8599;</span>
                </a>
            @endcan
            @can('cms.view')
                <a href="{{ route('admin.pages.index') }}" class="admin-quick-link">
                    <span class="admin-quick-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 3.5V8h4m-9 4h6m-6 4h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <span class="admin-quick-link__copy"><strong>{{ __('Website content') }}</strong><small>{{ __('Edit pages, FAQs, and travel guides') }}</small></span>
                    <span class="admin-quick-link__arrow" aria-hidden="true">&#8599;</span>
                </a>
            @endcan
            @can('settings.manage')
                <a href="{{ route('admin.branding.edit') }}" class="admin-quick-link">
                    <span class="admin-quick-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5 14 9l5.5 2-5.5 2-2 5.5-2-5.5-5.5-2L10 9l2-5.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m19 16 .9 2.1L22 19l-2.1.9L19 22l-.9-2.1L16 19l2.1-.9L19 16Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
                    <span class="admin-quick-link__copy"><strong>{{ __('Branding') }}</strong><small>{{ __('Customize your public website') }}</small></span>
                    <span class="admin-quick-link__arrow" aria-hidden="true">&#8599;</span>
                </a>
            @endcan
        </div>
    </section>
</x-admin::layouts.app>
