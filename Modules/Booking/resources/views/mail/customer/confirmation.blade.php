{{-- Markdown mail: keep lines flush-left — 4+ spaces of indentation turns a line into a code block.
     Rendered in the customer's locale (see BookingConfirmationMail). --}}
@php
    $isStay = $booking->isStay();
    $currency = $booking->currency_code;
    $money = fn ($value) => number_format((float) $value, 2).' '.$currency;
    $balance = max(0, (float) $booking->total_amount - (float) $booking->amount_paid);
    $location = $booking->pickup_type === 'office' ? $booking->businessLocation : null;
@endphp
<x-mail::message>
# {{ __('core::front.booking_confirmation_title') }}

{{ __('core::front.email_confirm_greeting', ['name' => $md($booking->customer?->full_name)]) }}

{{ __($isStay ? 'core::front.email_confirm_intro_stay' : 'core::front.email_confirm_intro', ['app' => config('app.name')]) }}

<x-mail::panel>
{{ __('core::front.booking_confirmation_reference') }}: **{{ $booking->reference }}**
</x-mail::panel>

<x-mail::table>
| | |
|:--|:--|
@if ($isStay)
| **{{ __('core::front.booking_check_in') }}** | {{ $booking->start_at->translatedFormat('D j M Y') }} |
| **{{ __('core::front.booking_check_out') }}** | {{ $booking->checkOutDate()->translatedFormat('D j M Y') }} |
| **{{ __('core::front.receipt_nights') }}** | {{ $days }} |
| **{{ __('core::front.booking_status_unit') }}** | {{ $md($booking->vehicle?->name) }} |
@else
@if ($booking->isHourly())
| **{{ __('core::front.booking_status_dates') }}** | {{ $booking->rentalPeriod('D j M Y') }} |
@else
| **{{ __('core::front.booking_status_dates') }}** | {{ $booking->start_at->translatedFormat('D j M Y') }} → {{ $booking->end_at->translatedFormat('D j M Y') }} |
| **{{ __('core::front.receipt_days') }}** | {{ $days }} |
@endif
| **{{ __('core::front.booking_status_vehicle') }}** | {{ $md($booking->vehicle?->name) }} |
| **{{ __('core::front.receipt_pickup') }}** | {{ $booking->pickup_type === 'delivery' ? __('core::front.booking_pickup_delivery').($booking->deliveryZone ? ' — '.$md($booking->deliveryZone->name) : '') : __('core::front.booking_pickup_office').($location ? ' — '.$md($location->name) : '') }} |
@endif
| **{{ __('core::front.booking_status_package') }}** | {{ $md($booking->package?->name ?? '—') }} |
@if ($booking->addons->isNotEmpty())
| **{{ __('core::front.email_confirm_addons') }}** | {{ $booking->addons->map(fn ($line) => $md($line->addon?->name ?? '#'.$line->addon_id).' × '.$line->quantity)->implode(', ') }} |
@endif
| **{{ __('core::front.email_confirm_total') }}** | **{{ $money($booking->total_amount) }}** |
@if ((float) $booking->deposit_amount > 0)
| **{{ __('core::front.receipt_deposit') }}** | {{ $money($booking->deposit_amount) }} |
@endif
@if ($balance > 0)
| **{{ __($isStay ? 'core::front.receipt_balance_due_stay' : 'core::front.receipt_balance_due') }}** | {{ $money($balance) }} |
@endif
</x-mail::table>

@php $place = $isStay ? $booking->vehicle : $location; @endphp
@if ($place?->hasLocation())
**{{ __('core::front.location_title') }}:** {{ $place->address ? $md($place->address).' — ' : '' }}[{{ __('core::front.location_open_map') }}]({{ $place->mapUrl() }})@if ($place->directionsUrl()) · [{{ __('core::front.location_directions') }}]({{ $place->directionsUrl() }})@endif

@endif
{{ __($isStay ? 'core::front.booking_confirmation_next_steps_stay' : 'core::front.booking_confirmation_next_steps') }}

<x-mail::button :url="$bookingUrl">
{{ __('core::front.email_confirm_view_booking') }}
</x-mail::button>

[{{ __('core::front.email_confirm_download_receipt') }}]({{ $receiptUrl }})

{{ __('core::front.email_confirm_contact', ['phone' => config('core.business.phone')]) }}

{{ __('core::front.email_confirm_signoff', ['app' => config('app.name')]) }}
</x-mail::message>
