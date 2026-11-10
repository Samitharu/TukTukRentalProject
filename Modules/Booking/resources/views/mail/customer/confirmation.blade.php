{{-- Markdown mail: keep lines flush-left — 4+ spaces of indentation turns a line into a code block.
     Rendered in the customer's locale (see BookingConfirmationMail). --}}
@php
    $isStay = $booking->isStay();
    $balance = max(0, (float) $booking->total_amount - (float) $booking->amount_paid);
@endphp
<x-mail::message>
# {{ __('core::front.booking_confirmation_title') }}

{{ __('core::front.email_confirm_greeting', ['name' => $md($booking->customer?->full_name)]) }}

{{ __(match (true) {
    $isStay => 'core::front.email_confirm_intro_stay',
    $booking->isTransfer() => 'core::front.email_confirm_intro_transfer',
    $booking->isActivity() => 'core::front.email_confirm_intro_activity',
    default => 'core::front.email_confirm_intro',
}, ['app' => config('app.name')]) }}

<x-mail::panel>
{{ __('core::front.booking_confirmation_reference') }}: **{{ $booking->reference }}**
</x-mail::panel>

@php $licenceBooking = $booking->checkoutBookings()->first(fn ($item) => $item->licence_documents_required && $item->status !== $item::STATUS_CANCELLED); @endphp
@if ($licenceBooking?->awaitsLicenceDocuments())
## {{ __('core::front.licence_next_title') }}

{{ __('core::front.licence_next_text', ['reference' => $licenceBooking->reference]) }}

[{{ __('core::front.licence_send_whatsapp') }}]({{ $licenceBooking->licenceWhatsAppUrl() }}) · [{{ __('core::front.licence_send_email') }}]({{ $licenceBooking->licenceEmailUrl() }}) ({{ config('core.business.email') }})

@endif

<x-mail::table>
| | |
|:--|:--|
@if ($booking->isTransfer())
| **{{ __('core::front.transfer_direction') }}** | {{ $booking->transferRouteLabel() }} |
| **{{ __('core::front.transfer_pickup_time') }}** | {{ $booking->start_at->translatedFormat('D j M Y') }}, {{ $booking->start_at->format('H:i') }} |
| **{{ __('core::front.transfer_flight_number') }}** | {{ $md($booking->flight_number) }} |
| **{{ __('core::front.transfer_area') }}** | {{ $md($booking->destinationLabel()) }} |
| **{{ __('core::front.transfer_address') }}** | {{ $md($booking->transfer_address) }} |
@if ($booking->vehicle)
| **{{ __('core::front.booking_status_vehicle') }}** | {{ $md($booking->vehicle->name) }} |
@endif
@elseif ($isStay)
| **{{ __('core::front.booking_check_in') }}** | {{ $booking->start_at->translatedFormat('D j M Y') }} |
| **{{ __('core::front.booking_check_out') }}** | {{ $booking->checkOutDate()->translatedFormat('D j M Y') }} |
| **{{ __('core::front.receipt_nights') }}** | {{ $days }} |
@if ($booking->vehicle)
| **{{ __('core::front.booking_status_unit') }}** | {{ $md($booking->vehicle->name) }} |
@endif
@elseif ($booking->isActivity())
| **{{ __('core::front.booking_activity_date') }}** | {{ $booking->rentalPeriod('D j M Y') }} |
@else
@if ($booking->isHourly())
| **{{ __('core::front.booking_status_dates') }}** | {{ $booking->rentalPeriod('D j M Y') }} |
@else
| **{{ __('core::front.booking_status_dates') }}** | {{ $booking->start_at->translatedFormat('D j M Y') }} → {{ $booking->end_at->translatedFormat('D j M Y') }} |
| **{{ __('core::front.receipt_days') }}** | {{ $days }} |
@endif
@if ($booking->vehicle)
| **{{ __('core::front.booking_status_vehicle') }}** | {{ $md($booking->vehicle->name) }} |
@endif
| **{{ __('core::front.receipt_pickup') }}** | {{ $booking->pickup_type === 'delivery' ? __('core::front.booking_pickup_delivery').($booking->deliveryZone ? ' — '.$md($booking->deliveryZone->name) : '') : __('core::front.booking_pickup_office') }} |
@endif
| **{{ __('core::front.booking_status_package') }}** | {{ $md($booking->package?->name ?? '—') }} |
@if ($booking->addons->isNotEmpty())
| **{{ __('core::front.email_confirm_addons') }}** | {{ $booking->addons->map(fn ($line) => $md($line->addon?->name ?? '#'.$line->addon_id).' × '.$line->quantity)->implode(', ') }} |
@endif
@if ($booking->displayOfferDiscount())
| **{{ __('core::front.offer_saving') }}** | {{ $booking->displayOfferDiscount() }} |
@endif
| **{{ __('core::front.email_confirm_total') }}** | **{{ $booking->displayTotal() }}** |
@php $together = $booking->togetherBookings(); @endphp
@foreach ($together as $linked)
| **{{ __('core::front.offer_also_booked') }}** | {{ $linked->reference }} — {{ $md($linked->package?->name ?? '—') }} — {{ $linked->displayTotal() }} |
@endforeach
@if ($together->isNotEmpty())
| **{{ __('core::front.offer_total_all') }}** | **{{ $booking->displayTotalWithLinked() }}** |
@endif
@if ((float) $booking->deposit_amount > 0)
| **{{ __('core::front.receipt_deposit') }}** | {{ \Modules\Pricing\Support\Money::usd($booking->deposit_amount) }} |
@endif
@if ($balance > 0)
| **{{ __($isStay ? 'core::front.receipt_balance_due_stay' : ($booking->isActivity() ? 'core::front.receipt_balance_due_activity' : 'core::front.receipt_balance_due')) }}** | {{ $booking->displayBalance() }} |
@endif
</x-mail::table>

@php $place = $isStay ? $booking->vehicle : null; @endphp
@if ($place?->hasLocation())
**{{ __('core::front.location_title') }}:** {{ $place->address ? $md($place->address).' — ' : '' }}[{{ __('core::front.location_open_map') }}]({{ $place->mapUrl() }})@if ($place->directionsUrl()) · [{{ __('core::front.location_directions') }}]({{ $place->directionsUrl() }})@endif

@elseif ($booking->officeAddress())
**{{ __('core::front.location_title') }}:** {{ $md($booking->officeAddress()) }} — [{{ __('core::front.location_open_map') }}]({{ $booking->officeMapUrl() }})

@endif
{{ __(match (true) {
    $isStay => 'core::front.booking_confirmation_next_steps_stay',
    $booking->isTransfer() => 'core::front.booking_confirmation_next_steps_transfer',
    $booking->isActivity() => 'core::front.booking_confirmation_next_steps_activity',
    default => 'core::front.booking_confirmation_next_steps',
}) }}

<x-mail::button :url="$bookingUrl">
{{ __('core::front.email_confirm_view_booking') }}
</x-mail::button>

[{{ __('core::front.email_confirm_download_receipt') }}]({{ $receiptUrl }})

{{ __('core::front.email_confirm_contact', ['phone' => config('core.business.phone')]) }}

{{ __('core::front.email_confirm_signoff', ['app' => config('app.name')]) }}
</x-mail::message>
