{{-- Markdown mail: keep lines flush-left — 4+ spaces of indentation turns a line into a code block. --}}
<x-mail::message>
# {{ __('New booking received') }}

@if ($booking->isTransfer())
**{{ $booking->reference }}** — {{ __('Airport pickup') }} {{ $booking->rentalPeriod('D j M Y') }}
@elseif ($booking->isStay())
**{{ $booking->reference }}** — {{ __('Check-in') }} {{ $booking->start_at->format('D j M Y') }} → {{ __('check-out') }} {{ $booking->checkOutDate()->format('D j M Y') }} ({{ trans_choice(':count night|:count nights', $days, ['count' => $days]) }})
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
| **{{ __('Area') }}** | {{ $md($booking->transferArea?->name ?? '—') }} |
| **{{ __('Address') }}** | {{ $md($booking->transfer_address) }} |
| **{{ __('Vehicle') }}** | {{ $booking->vehicle?->name }} ({{ $booking->vehicle?->plate_no }}) |
| **{{ __('Package') }}** | {{ $booking->package?->name ?? '—' }} |
@elseif ($booking->isStay())
| **{{ __('Stay') }}** | {{ $md($booking->vehicle?->name) }}{{ $booking->vehicle?->address ? ' — '.$md($booking->vehicle->address) : '' }} |
| **{{ __('Package') }}** | {{ $booking->package?->name ?? '—' }} |
@else
| **{{ __('Vehicle') }}** | {{ $booking->vehicle ? $booking->vehicle->name.' ('.$booking->vehicle->plate_no.')' : '⚠ '.__('Needs a vehicle — none was free; assign one in the control panel') }} |
| **{{ __('Package') }}** | {{ $booking->package?->name ?? '—' }} |
| **{{ __('Pickup') }}** | {{ $booking->pickup_type === 'delivery' ? __('Delivery').': '.($booking->deliveryZone?->name ?? '—') : __('Office').': '.($booking->businessLocation?->name ?? '—') }} |
| **{{ __('International permit') }}** | {{ $booking->has_international_permit ? __('Yes') : __('No') }} |
@endif
| **{{ __('Status') }}** | {{ ucfirst(str_replace('_', ' ', $booking->status)) }} |
| **{{ __('Total') }}** | {{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency_code }} |
@if ((float) $booking->deposit_amount > 0)
| **{{ __('Deposit') }}** | {{ number_format((float) $booking->deposit_amount, 2) }} {{ $booking->currency_code }} |
@endif
</x-mail::table>

@if ($booking->addons->isNotEmpty())
**{{ __('Add-ons') }}:** {{ $booking->addons->map(fn ($line) => ($line->addon?->name ?? '#'.$line->addon_id).' × '.$line->quantity)->implode(', ') }}
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
