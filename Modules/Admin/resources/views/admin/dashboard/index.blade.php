<x-admin::layouts.app :title="__('Dashboard')">
    <div class="admin-card">
        <p>{{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}</p>
        <p>{{ __('The real dashboard (today\'s pickups/returns, revenue, occupancy, alerts) is built in Phase 7 once the Fleet, Booking and Payment modules exist.') }}</p>
    </div>
</x-admin::layouts.app>
