{{-- "200 km included · then 0.50 LKR per extra km" — only for packages with a km allowance and an extra-km rate. --}}
@if ($package->hasKmAllowance())
    <p class="km-allowance">
        {{ __($package->included_km_per_day ? 'core::front.packages_km_included_per_day' : 'core::front.packages_km_included', ['km' => number_format($package->included_km)]) }}
        &middot;
        {{ __('core::front.packages_extra_km', ['rate' => $package->extra_km_rate, 'currency' => config('pricing.default_currency')]) }}
    </p>
@endif
