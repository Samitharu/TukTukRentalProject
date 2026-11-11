@php
    $p = $booking->price_breakdown ?? [];
    $e = $p['eur'] ?? [];
    $money = fn ($usd, $eur = null) => \Modules\Pricing\Support\Money::dual($usd, $eur);
@endphp
<h2>{{ __('core::front.receipt_charges') }}</h2>
<table class="lines">
    <tr><td>{{ __(match (true) {
        $booking->isStay() => 'core::front.price_accommodation',
        $booking->isTransfer() => 'core::front.price_airport_pickup',
        $booking->isActivity() => 'core::front.price_activity',
        default => 'core::front.price_base_amount',
    }) }}</td><td class="amt">{{ $money($p['base_amount'] ?? $booking->total_amount, $e['base_amount'] ?? null) }}</td></tr>

    @if ((float) ($p['seasonal_adjustment'] ?? 0) != 0)
        <tr><td>{{ __('core::front.price_seasonal_adjustment') }}</td><td class="amt">{{ $money($p['seasonal_adjustment'], $e['seasonal_adjustment'] ?? null) }}</td></tr>
    @endif

    @foreach ($p['addon_lines'] ?? [] as $line)
        <tr>
            <td>{{ is_array($line['name'] ?? null) ? (reset($line['name']) ?: '') : ($line['name'] ?? '') }} × {{ $line['quantity'] ?? 1 }}</td>
            <td class="amt">{{ $money($line['amount'] ?? 0, $line['amount_eur'] ?? null) }}</td>
        </tr>
    @endforeach

    @if ((float) ($p['transfer_fee'] ?? 0) > 0)
        <tr><td>{{ __('core::front.price_transfer_fee') }}@if ($booking->transferArea) — {{ $booking->transferArea->name }}@endif</td><td class="amt">{{ $money($p['transfer_fee'], $e['transfer_fee'] ?? null) }}</td></tr>
    @endif

    @if ((float) ($p['licence_fee'] ?? 0) > 0)
        <tr><td>{{ __('core::front.price_licence_fee') }}@if ((int) ($p['drivers'] ?? 1) > 1) × {{ (int) $p['drivers'] }}@endif</td><td class="amt">{{ $money($p['licence_fee'], $e['licence_fee'] ?? null) }}</td></tr>
    @endif

    @if ((float) ($p['delivery_fee'] ?? 0) > 0)
        <tr><td>{{ __('core::front.price_delivery_fee') }}</td><td class="amt">{{ $money($p['delivery_fee']) }}</td></tr>
    @endif

    {{-- Only older bookings, from when coupons existed, have a discount. --}}
    @if ((float) ($p['coupon_discount'] ?? 0) > 0)
        <tr><td>{{ __('core::front.price_coupon_discount') }} ({{ $p['coupon_code'] ?? '' }})</td><td class="amt">-{{ $money($p['coupon_discount']) }}</td></tr>
    @endif

    @if ($booking->displayOfferDiscount())
        <tr><td>{{ __('core::front.offer_saving') }}</td><td class="amt">{{ $booking->displayOfferDiscount() }}</td></tr>
    @endif

    <tr class="total"><td>{{ __('core::front.price_total') }}</td><td class="amt">{{ $booking->displayTotal() }}</td></tr>
</table>
