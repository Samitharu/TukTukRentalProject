{{-- One row's price in a package's price table. With a minimum of several hours, the price for those hours, then the hourly rate for longer bookings. --}}
{{ $package->tierPrice($tier) }} {{ $package->tierPriceSuffix($tier) }}
@if ($package->pricedHours($tier) !== null)
    <br><small class="text-muted" style="font-weight:400;">({{ $tier->displayPrice() }} {{ __($package->priceSuffixKey()) }})</small>
@endif
