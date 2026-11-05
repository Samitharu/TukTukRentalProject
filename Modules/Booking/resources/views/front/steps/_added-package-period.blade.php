{{-- When a package added on the review step takes place, from its normalized fields. --}}
<p class="text-muted">
    @if ($added->isTransfer())
        {{ \Illuminate\Support\Carbon::parse($data['start_date'])->format('d M Y') }}, {{ $data['transfer_time'] }} &middot; {{ __($data['transfer_direction'] === 'departure' ? 'core::front.transfer_departure' : 'core::front.transfer_arrival') }} &middot; {{ $data['flight_number'] }} &middot; {{ $data['transfer_address'] }}
    @elseif ($added->isActivity())
        {{ \Illuminate\Support\Carbon::parse($data['start_date'])->format('d M Y') }}@if (isset($data['people'])) ({{ trans_choice('core::front.booking_people_count', (int) $data['people'], ['count' => (int) $data['people']]) }})@endif
    @elseif ($added->isHourly())
        {{ \Illuminate\Support\Carbon::parse($data['start_date'])->format('d M Y') }}, {{ $data['start_time'] }} ({{ trans_choice('core::front.booking_hours_count', (int) $data['hours'], ['count' => (int) $data['hours']]) }})
    @elseif ($added->isStay())
        {{ __('core::front.booking_check_in') }}: {{ \Illuminate\Support\Carbon::parse($data['start_date'])->format('d M Y') }} &rarr; {{ __('core::front.booking_check_out') }}: {{ \Illuminate\Support\Carbon::parse($data['end_date'])->addDay()->format('d M Y') }}
    @else
        {{ \Illuminate\Support\Carbon::parse($data['start_date'])->format('d M Y') }} &rarr; {{ \Illuminate\Support\Carbon::parse($data['end_date'])->format('d M Y') }}
    @endif
</p>
