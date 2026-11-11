{{-- Markdown mail: keep lines flush-left — 4+ spaces of indentation turns a line into a code block. --}}
<x-mail::message>
# {{ __('New booking received') }}

@if ($booking->isTransfer())
**{{ $booking->reference }}** — {{ __('Airport pickup') }} {{ $booking->rentalPeriod('D j M Y') }}
@elseif ($booking->isStay())
**{{ $booking->reference }}** — {{ __('Check-in') }} {{ $booking->start_at->format('D j M Y') }} → {{ __('check-out') }} {{ $booking->checkOutDate()->format('D j M Y') }} ({{ trans_choice(':count night|:count nights', $days, ['count' => $days]) }})
@elseif ($booking->isActivity())
**{{ $booking->reference }}** — {{ $booking->rentalPeriod('D j M Y') }}
@else
@if ($booking->isHourly())
**{{ $booking->reference }}** — {{ $booking->rentalPeriod('D j M Y') }}
@else
**{{ $booking->reference }}** — {{ $booking->start_at->format('D j M Y') }} → {{ $booking->end_at->format('D j M Y') }} ({{ trans_choice(':count day|:count days', $days, ['count' => $days]) }})
@endif
@endif

<x-mail::table>
| | |
|:--|:--|
| **{{ __('Customer') }}** | {{ $md($booking->customer?->full_name) }} |
| **{{ __('Email') }}** | {{ $md($booking->customer?->email) }} |
| **{{ __('Phone') }}** | {{ $md($booking->customer?->phone ?? '—') }} |
| **{{ __('Nationality') }}** | {{ $md($booking->customer?->nationality ?? '—') }} |
@if ($booking->isTransfer())
| **{{ __('Direction') }}** | {{ $booking->transfer_direction === 'departure' ? __('Address → airport (departure)') : __('Airport → address (arrival)') }} |
| **{{ __('Flight') }}** | {{ $md($booking->flight_number) }} |
| **{{ __('Destination') }}** | {{ $md($booking->destinationLabel()) }} |
| **{{ __('Address') }}** | {{ $md($booking->transfer_address) }} |
| **{{ __('Service') }}** | {{ $booking->serviceLabel() }} |
| **{{ __('Package') }}** | {{ $booking->package?->name ?? '—' }} |
@elseif ($booking->isStay())
| **{{ __('Service') }}** | {{ $md($booking->serviceLabel()) }}{{ $booking->vehicle?->address ? ' — '.$md($booking->vehicle->address) : '' }} |
| **{{ __('Package') }}** | {{ $booking->package?->name ?? '—' }} |
@elseif ($booking->isActivity())
| **{{ __('Service') }}** | {{ $booking->serviceLabel() }} |
| **{{ __('Package') }}** | {{ $booking->package?->name ?? '—' }} |
@else
| **{{ __('Service') }}** | {{ $booking->serviceLabel() }} |
| **{{ __('Package') }}** | {{ $booking->package?->name ?? '—' }} |
| **{{ __('Pickup') }}** | {{ $booking->pickup_type === 'delivery' ? __('Delivery').': '.($booking->deliveryZone?->name ?? '—') : __('Office') }} |
| **{{ __('International permit') }}** | {{ $booking->has_international_permit ? __('Yes') : __('No') }} |
@endif
| **{{ __('Status') }}** | {{ ucfirst(str_replace('_', ' ', $booking->status)) }} |
@if ($booking->displayOfferDiscount())
| **{{ __('Offer saving') }}** | {{ $booking->displayOfferDiscount() }} |
@endif
| **{{ __('Total') }}** | {{ $booking->adminTotal() }} |
@php $together = $booking->togetherBookings(); @endphp
@foreach ($together as $linked)
| **{{ __('Booked together with') }}** | {{ $linked->reference }} — {{ $md($linked->package?->name ?? '—') }} — {{ $linked->adminTotal() }} |
@endforeach
@if ($together->isNotEmpty())
| **{{ __('Total for all bookings') }}** | **{{ $booking->adminTotalWithLinked() }}** |
@endif
@if ((float) $booking->deposit_amount > 0)
| **{{ __('Deposit') }}** | {{ \Modules\Pricing\Support\Money::usd($booking->deposit_amount) }} |
@endif
</x-mail::table>

@if ($booking->addons->isNotEmpty())
**{{ __('Add-ons') }}:** {{ $booking->addons->map(fn ($line) => ($line->addon?->name ?? '#'.$line->addon_id).' × '.$line->quantity)->implode(', ') }}
@endif

@if ($booking->licence_documents_required)
**{{ __('Driving licence') }}:** @if ($booking->drivers()){{ trans_choice(':count driver|:count drivers', $booking->drivers(), ['count' => $booking->drivers()]) }}. @endif{{ __('the customer agreed to send it by WhatsApp or email for the Sri Lankan permit. Mark it received on the booking once it arrives.') }}

@endif
@if (filled($booking->special_requests))
<x-mail::panel>
**{{ __('Special requests') }}:** {{ $md($booking->special_requests) }}
</x-mail::panel>
@endif

<x-mail::button :url="$adminUrl">
{{ __('Open booking in control panel') }}
</x-mail::button>

{{ __('Replying to this email goes straight to the customer.') }}
</x-mail::message>
