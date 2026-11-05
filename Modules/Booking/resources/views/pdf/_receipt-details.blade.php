@php
    $p = $booking->price_breakdown ?? [];
    $isStay = $booking->isStay();
    // Rental days, or nights for a stay (end_at is its last night).
    $days = $p['days'] ?? $booking->lengthInDays();
    $pickup = $booking->pickup_type === 'delivery'
        ? __('core::front.booking_pickup_delivery').($booking->deliveryZone ? ' — '.$booking->deliveryZone->name : '')
        : __('core::front.booking_pickup_office').($booking->officeAddress() ? ' — '.$booking->officeAddress() : '');
@endphp
@if ($booking->isTransfer())
    <h2>{{ __('core::front.receipt_transfer') }}</h2>
    <table class="kv">
        <tr><td class="k">{{ __('core::front.transfer_direction') }}</td><td>{{ $booking->transferRouteLabel() }}</td></tr>
        <tr><td class="k">{{ __('core::front.transfer_pickup_time') }}</td><td>{{ $booking->rentalPeriod() }}</td></tr>
        <tr><td class="k">{{ __('core::front.transfer_flight_number') }}</td><td>{{ $booking->flight_number }}</td></tr>
        <tr><td class="k">{{ __('core::front.transfer_area') }}</td><td>{{ $booking->transferArea?->name }}</td></tr>
        <tr><td class="k">{{ __('core::front.transfer_address') }}</td><td>{{ $booking->transfer_address }}</td></tr>
        @if ($booking->package)
            <tr><td class="k">{{ __('core::front.booking_review_package') }}</td><td>{{ $booking->package->name }}</td></tr>
        @endif
        @if ($booking->vehicle)
            <tr><td class="k">{{ __('core::front.receipt_vehicle') }}</td><td>{{ $booking->vehicle->name }} ({{ $booking->vehicle->plate_no }})</td></tr>
        @endif
    </table>
@elseif ($isStay)
    <h2>{{ __('core::front.receipt_stay') }}</h2>
    <table class="kv">
        <tr><td class="k">{{ __('core::front.booking_check_in') }}</td><td>{{ $booking->start_at->format('d M Y') }}</td></tr>
        <tr><td class="k">{{ __('core::front.booking_check_out') }}</td><td>{{ $booking->checkOutDate()->format('d M Y') }}</td></tr>
        <tr><td class="k">{{ __('core::front.receipt_nights') }}</td><td>{{ $days }}</td></tr>
        @if ($booking->package)
            <tr><td class="k">{{ __('core::front.booking_review_package') }}</td><td>{{ $booking->package->name }}</td></tr>
        @endif
        @if ($booking->vehicle)
            <tr><td class="k">{{ __('core::front.receipt_unit') }}</td><td>{{ $booking->vehicle->name }}</td></tr>
        @endif
        @if ($booking->vehicle?->address || $booking->vehicle?->hasCoordinates())
            <tr><td class="k">{{ __('core::front.receipt_location') }}</td><td>{{ $booking->vehicle->address }}@if ($booking->vehicle->hasCoordinates())<br><span class="muted">{{ $booking->vehicle->lat }}, {{ $booking->vehicle->lng }}</span>@endif</td></tr>
        @endif
    </table>
@elseif ($booking->isActivity())
    <h2>{{ __('core::front.receipt_activity') }}</h2>
    <table class="kv">
        <tr><td class="k">{{ __('core::front.booking_activity_date') }}</td><td>{{ $booking->rentalPeriod() }}</td></tr>
        @if ($booking->package)
            <tr><td class="k">{{ __('core::front.booking_review_package') }}</td><td>{{ $booking->package->name }}</td></tr>
        @endif
    </table>
@else
    <h2>{{ __('core::front.receipt_rental') }}</h2>
    <table class="kv">
        <tr><td class="k">{{ __('core::front.booking_review_dates') }}</td><td>{{ $booking->rentalPeriod() }}</td></tr>
        @unless ($booking->isHourly())
            <tr><td class="k">{{ __('core::front.receipt_days') }}</td><td>{{ $days }}</td></tr>
        @endunless
        @if ($booking->included_km !== null)
            <tr><td class="k">{{ __('core::front.receipt_km_included') }}</td><td>{{ number_format($booking->included_km) }} km</td></tr>
        @endif
        @if ($booking->package)
            <tr><td class="k">{{ __('core::front.booking_review_package') }}</td><td>{{ $booking->package->name }}</td></tr>
        @endif
        @if ($booking->vehicle)
            <tr><td class="k">{{ __('core::front.receipt_vehicle') }}</td><td>{{ $booking->vehicle->name }} ({{ $booking->vehicle->plate_no }})</td></tr>
        @endif
        <tr><td class="k">{{ __('core::front.receipt_pickup') }}</td><td>{{ $pickup }}</td></tr>
    </table>
@endif
