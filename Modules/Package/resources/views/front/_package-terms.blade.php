{{-- The small print under a package's price: length limits, hourly window, km allowance. --}}
<div class="package-terms text-muted">
    @if ($package->isHourly())
        <p>{{ __('core::front.packages_hours_range', ['min' => $package->min_hours ?? 1, 'max' => $package->max_hours ?? (config('booking.hourly.last_return_hour') - config('booking.hourly.first_start_hour'))]) }}</p>
    @elseif (! $package->isActivity() && $package->min_days > 1)
        <p>{{ __($package->isStay() ? 'core::front.packages_min_nights' : 'core::front.packages_min_days', ['count' => $package->min_days]) }}</p>
    @endif
    @include('package::front._km-allowance', ['package' => $package])
</div>
