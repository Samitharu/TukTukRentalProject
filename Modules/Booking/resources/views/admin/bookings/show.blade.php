<x-admin::layouts.app :title="__('Booking :reference', ['reference' => $booking->reference])">
    <div class="admin-card">
        <p><strong>{{ __('Status') }}:</strong> {{ ucfirst(str_replace('_', ' ', $booking->status)) }}</p>
        <p><strong>{{ __('Customer') }}:</strong> <a href="{{ route('admin.customers.show', $booking->customer) }}">{{ $booking->customer->full_name }}</a> ({{ $booking->customer->email }})</p>
        <p><strong>{{ $booking->isStay() ? __('Stay') : __('Vehicle') }}:</strong> {{ $booking->vehicle->adminLabel() }}</p>
        <p><strong>{{ __('Package') }}:</strong> {{ $booking->package?->name }}</p>
        @if ($booking->isStay())
            <p><strong>{{ __('Check-in') }}:</strong> {{ $booking->start_at->format('Y-m-d') }}</p>
            <p><strong>{{ __('Check-out') }}:</strong> {{ $booking->checkOutDate()->format('Y-m-d') }} ({{ trans_choice(':count night|:count nights', $booking->lengthInDays(), ['count' => $booking->lengthInDays()]) }})</p>
            @if ($booking->vehicle->mapUrl())
                <p><strong>{{ __('Location') }}:</strong> <a href="{{ $booking->vehicle->mapUrl() }}" target="_blank" rel="noopener noreferrer">{{ $booking->vehicle->address ?: __('View on Google Maps') }}</a></p>
            @endif
        @elseif ($booking->isHourly())
            <p><strong>{{ __('Hourly rental') }}:</strong> {{ $booking->rentalPeriod('Y-m-d') }} — {{ __('tuk tuk blocked for the whole day') }}</p>
        @else
            <p><strong>{{ __('Pickup') }}:</strong> {{ $booking->start_at->format('Y-m-d') }}</p>
            <p><strong>{{ __('Return') }}:</strong> {{ $booking->end_at->format('Y-m-d') }}</p>
        @endif
        <p><strong>{{ __('Total') }}:</strong> {{ $booking->total_amount }} {{ $booking->currency_code }} ({{ __('deposit') }}: {{ $booking->deposit_amount }})</p>
        @if ($booking->extraCharges->isNotEmpty())
            <p><strong>{{ __('Extra charges') }}:</strong></p>
            <ul>
                @foreach ($booking->extraCharges as $charge)
                    <li>{{ ucfirst(str_replace('_', ' ', $charge->type)) }} — {{ $charge->amount }} {{ $booking->currency_code }}@if ($charge->notes) ({{ $charge->notes }})@endif</li>
                @endforeach
            </ul>
            <p><strong>{{ __('Total incl. extra charges') }}:</strong> {{ number_format((float) $booking->total_amount + (float) $booking->extraCharges->sum('amount'), 2, '.', '') }} {{ $booking->currency_code }}</p>
        @endif
        @unless ($booking->isStay())
            <p><strong>{{ __('International Driving Permit') }}:</strong> {{ $booking->has_international_permit ? __('Yes') : __('No') }}</p>
        @endunless
        @if ($booking->special_requests)
            <p><strong>{{ __('Special requests') }}:</strong> {{ $booking->special_requests }}</p>
        @endif

        <div style="margin-top:1rem;">
            @if ($booking->status === \Modules\Booking\Models\Booking::STATUS_CONFIRMED)
                <form method="POST" action="{{ route('admin.bookings.activate', $booking) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Mark picked up') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.bookings.no-show', $booking) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Mark no-show') }}</button>
                </form>
            @endif

            @if ($booking->status === \Modules\Booking\Models\Booking::STATUS_ACTIVE)
                <form method="POST" action="{{ route('admin.bookings.complete', $booking) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Mark returned') }}</button>
                </form>
            @endif

            @if (in_array($booking->status, [\Modules\Booking\Models\Booking::STATUS_HOLD, \Modules\Booking\Models\Booking::STATUS_PENDING_PAYMENT, \Modules\Booking\Models\Booking::STATUS_CONFIRMED], true))
                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" style="display:inline;" onsubmit="return confirm('{{ __('Cancel this booking?') }}');">
                    @csrf
                    <input type="hidden" name="reason" value="{{ __('Cancelled by admin') }}">
                    <button type="submit" class="admin-btn admin-btn--danger">{{ __('Cancel booking') }}</button>
                </form>
            @endif
        </div>
    </div>

    @if ($booking->addons->isNotEmpty())
        <div class="admin-card">
            <h2>{{ __('Add-ons') }}</h2>
            <ul>
                @foreach ($booking->addons as $addon)
                    <li>{{ $addon->addon->name }} × {{ $addon->quantity }} — {{ $addon->unit_price }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($booking->hasKmAllowance())
        <div class="admin-card">
            <h2>{{ __('Odometer') }}</h2>
            <p class="admin-hint" style="margin-top:0;">
                {{ __(':km km included, then :rate :currency per extra km.', ['km' => number_format($booking->included_km), 'rate' => $booking->extra_km_rate, 'currency' => $booking->currency_code]) }}
                @if ($booking->kmDriven() !== null)
                    <strong>{{ __('Driven: :km km', ['km' => number_format($booking->kmDriven())]) }}{{ $booking->extraKm() > 0 ? ' — '.__(':km km over', ['km' => number_format($booking->extraKm())]) : '' }}</strong>
                @endif
            </p>
            <form method="POST" action="{{ route('admin.bookings.odometer.update', $booking) }}">
                @csrf
                @method('PUT')
                <div class="admin-location__coords">
                    <div class="admin-form-field">
                        <label for="odometer_start">{{ __('Reading at pickup (km)') }}</label>
                        <input type="number" id="odometer_start" name="odometer_start" value="{{ old('odometer_start', $booking->odometer_start) }}" min="0">
                    </div>
                    <div class="admin-form-field">
                        <label for="odometer_end">{{ __('Reading at return (km)') }}</label>
                        <input type="number" id="odometer_end" name="odometer_end" value="{{ old('odometer_end', $booking->odometer_end) }}" min="0">
                    </div>
                </div>
                <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Save readings') }}</button>
            </form>
        </div>
    @endif

    <div class="admin-card">
        <h2>{{ __('Change dates') }}</h2>
        <form method="POST" action="{{ route('admin.bookings.dates.update', $booking) }}">
            @csrf
            @method('PUT')
            @if ($booking->isHourly())
                <div class="admin-form-field">
                    <label for="start_date">{{ __('New date') }}</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $booking->start_at->toDateString() }}" required>
                </div>
                <div class="admin-form-field">
                    <label for="start_time">{{ __('New start time (keeps the :hours booked)', ['hours' => trans_choice('core::front.booking_hours_count', $booking->hours(), ['count' => $booking->hours()])]) }}</label>
                    <select id="start_time" name="start_time" required>
                        @foreach ($hourlyStartTimes as $time)
                            <option value="{{ $time }}" @selected($booking->start_at->format('H:i') === $time)>{{ $time }}</option>
                        @endforeach
                    </select>
                </div>
            @else
            <div class="admin-form-field">
                <label for="start_date">{{ $booking->isStay() ? __('New check-in date') : __('New pickup date') }}</label>
                <input type="date" id="start_date" name="start_date" value="{{ $booking->start_at->toDateString() }}" required>
            </div>
            <div class="admin-form-field">
                <label for="end_date">{{ $booking->isStay() ? __('New check-out date') : __('New return date') }}</label>
                <input type="date" id="end_date" name="end_date" value="{{ $booking->checkOutDate()->toDateString() }}" required>
            </div>
            @endif
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Update dates') }}</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>{{ $booking->isStay() ? __('Move to another cabana / room') : __('Reassign vehicle') }}</h2>
        <form method="POST" action="{{ route('admin.bookings.vehicle.update', $booking) }}">
            @csrf
            @method('PUT')
            <div class="admin-form-field">
                <label for="vehicle_id">{{ $booking->isStay() ? __('Stay') : __('Vehicle') }}</label>
                <select id="vehicle_id" name="vehicle_id" required>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected($vehicle->id === $booking->vehicle_id)>{{ $vehicle->adminLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Reassign') }}</button>
        </form>
    </div>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('When') }}</th>
                    <th>{{ __('From') }}</th>
                    <th>{{ __('To') }}</th>
                    <th>{{ __('By') }}</th>
                    <th>{{ __('Reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($booking->statusHistory as $entry)
                    <tr>
                        <td>{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $entry->from_status }}</td>
                        <td>{{ $entry->to_status }}</td>
                        <td>{{ $entry->changedBy?->name ?? __('System') }}</td>
                        <td>{{ $entry->reason }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
