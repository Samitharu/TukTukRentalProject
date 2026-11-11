<x-admin::layouts.app :title="__('Booking :reference', ['reference' => $booking->reference])">
    @if ($flags !== [])
        <div class="admin-alert admin-alert--warn">
            <strong>⚠ {{ __('Possible spam booking') }}:</strong>
            {{ __(':threshold or more upcoming bookings share', ['threshold' => config('booking.suspicious_threshold')]) }}
            {{ implode(', ', array_map(fn ($label) => mb_strtolower(__($label)), $flags)) }}.
            {{ __('Check the related bookings below before cancelling — one traveller can book several legs of a trip.') }}
        </div>
    @endif

    <div class="admin-card">
        <p><strong>{{ __('Status') }}:</strong> {{ ucfirst(str_replace('_', ' ', $booking->status)) }}</p>
        <p><strong>{{ __('Customer') }}:</strong> <a href="{{ route('admin.customers.show', $booking->customer) }}">{{ $booking->customer->full_name }}</a> ({{ $booking->customer->email }})</p>
        <p><strong>{{ __('Service') }}:</strong> {{ $booking->serviceLabel() }}</p>
        <p><strong>{{ __('Package') }}:</strong> {{ $booking->package?->name }}</p>
        @if ($booking->isTransfer())
            <p><strong>{{ __('Airport pickup') }}:</strong> {{ $booking->transfer_direction === 'departure' ? __('Address → airport (departure)') : __('Airport → address (arrival)') }}</p>
            <p><strong>{{ __('Pickup time') }}:</strong> {{ $booking->start_at->format('Y-m-d H:i') }}</p>
            <p><strong>{{ __('Flight number') }}:</strong> {{ $booking->flight_number }}</p>
            <p><strong>{{ __('Destination') }}:</strong> {{ $booking->destinationLabel() }}</p>
            <p><strong>{{ __('Hotel / address') }}:</strong> {{ $booking->transfer_address }}</p>
        @elseif ($booking->isStay())
            <p><strong>{{ __('Check-in') }}:</strong> {{ $booking->start_at->format('Y-m-d') }}</p>
            <p><strong>{{ __('Check-out') }}:</strong> {{ $booking->checkOutDate()->format('Y-m-d') }} ({{ trans_choice(':count night|:count nights', $booking->lengthInDays(), ['count' => $booking->lengthInDays()]) }})</p>
            @if ($booking->vehicle?->mapUrl())
                <p><strong>{{ __('Location') }}:</strong> <a href="{{ $booking->vehicle->mapUrl() }}" target="_blank" rel="noopener noreferrer">{{ $booking->vehicle->address ?: __('View on Google Maps') }}</a></p>
            @endif
        @elseif ($booking->isActivity())
            <p><strong>{{ __('Activity date') }}:</strong> {{ $booking->rentalPeriod('Y-m-d') }}</p>
        @elseif ($booking->isHourly())
            <p><strong>{{ __('Hourly rental') }}:</strong> {{ $booking->rentalPeriod('Y-m-d') }} — {{ __('tuk tuk blocked for the whole day') }}</p>
        @else
            <p><strong>{{ __('Pickup') }}:</strong> {{ $booking->start_at->format('Y-m-d') }}</p>
            <p><strong>{{ __('Return') }}:</strong> {{ $booking->end_at->format('Y-m-d') }}</p>
        @endif
        @if ($booking->displayOfferDiscount())
            <p><strong>{{ __('Offer saving') }}:</strong> {{ $booking->displayOfferDiscount() }}</p>
        @endif
        <p><strong>{{ __('Total') }}:</strong> {{ $booking->adminTotal() }} ({{ __('deposit') }}: {{ \Modules\Pricing\Support\Money::usd($booking->deposit_amount) }})</p>
        @php $together = $booking->togetherBookings(); @endphp
        @foreach ($together as $linked)
            <p><strong>{{ __('Booked together with') }}:</strong> <a href="{{ route('admin.bookings.show', $linked) }}">{{ $linked->reference }}</a> — {{ $linked->package?->name }} — {{ $linked->adminTotal() }} ({{ ucfirst(str_replace('_', ' ', $linked->status)) }})</p>
        @endforeach
        @if ($together->isNotEmpty())
            <p><strong>{{ __('Total for all bookings') }}:</strong> {{ $booking->adminTotalWithLinked() }}</p>
        @endif
        @if ($booking->displayOfferDiscount() && $booking->linkedBooking?->status === \Modules\Booking\Models\Booking::STATUS_CANCELLED)
            <div class="admin-alert admin-alert--warn">{{ __('The other booking was cancelled, but this one still has its offer saving. Charge the difference if the offer no longer applies.') }}</div>
        @endif
        @if ($booking->extraCharges->isNotEmpty())
            <p><strong>{{ __('Extra charges') }}:</strong></p>
            <ul>
                @foreach ($booking->extraCharges as $charge)
                    <li>{{ ucfirst(str_replace('_', ' ', $charge->type)) }} — {{ \Modules\Pricing\Support\Money::admin($charge->amount, $charge->amount_eur) }}@if ($charge->notes) ({{ $charge->notes }})@endif</li>
                @endforeach
            </ul>
            <p><strong>{{ __('Total incl. extra charges') }}:</strong> {{ $booking->adminTotalWithExtras() }}</p>
        @endif
        @unless ($booking->isStay() || $booking->isTransfer())
            <p><strong>{{ __('International Driving Permit') }}:</strong> {{ $booking->has_international_permit ? __('Yes') : __('No') }}</p>
        @endunless
        @if ($booking->licence_documents_required)
            <div @class(['admin-alert', 'admin-alert--warn' => ! $booking->licence_documents_received_at, 'admin-alert--success' => $booking->licence_documents_received_at]) style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between;">
                <span>
                    <strong>{{ __('Driving licence (for the Sri Lankan permit)') }}:</strong>
                    @if ($booking->drivers())
                        {{ trans_choice(':count driver|:count drivers', $booking->drivers(), ['count' => $booking->drivers()]) }} —
                    @endif
                    {{ $booking->licence_documents_received_at
                        ? __('received :date', ['date' => $booking->licence_documents_received_at->timezone(config('booking.business_timezone'))->format('d M Y, H:i')])
                        : __('not received yet. The customer was asked to send it by WhatsApp or email.') }}
                </span>
                @can('update', $booking)
                    <form method="POST" action="{{ route('admin.bookings.licence.update', $booking) }}" style="margin:0;">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="received" value="{{ $booking->licence_documents_received_at ? '0' : '1' }}">
                        <button type="submit" @class(['admin-btn', 'admin-btn--secondary' => $booking->licence_documents_received_at])>{{ $booking->licence_documents_received_at ? __('Mark as not received') : __('Mark as received') }}</button>
                    </form>
                @endcan
            </div>
        @endif
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
                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" style="display:inline;" data-confirm="{{ __('Cancel this booking?') }}">
                    @csrf
                    <input type="hidden" name="reason" value="{{ __('Cancelled by admin') }}">
                    <button type="submit" class="admin-btn admin-btn--danger">{{ __('Cancel booking') }}</button>
                </form>
            @endif
        </div>
    </div>

    @include('booking::admin.bookings._source', ['booking' => $booking, 'related' => $related, 'canCancelThis' => $canCancelThis, 'ipBlock' => $ipBlock])

    @if ($booking->addons->isNotEmpty())
        <div class="admin-card">
            <h2>{{ __('Add-ons') }}</h2>
            <ul>
                @foreach ($booking->addons as $addon)
                    <li>{{ $addon->addon->name }} × {{ $addon->quantity }} — {{ \Modules\Pricing\Support\Money::dual($addon->unit_price, $addon->unit_price_eur) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($booking->hasKmAllowance())
        <div class="admin-card">
            <h2>{{ __('Odometer') }}</h2>
            <p class="admin-hint" style="margin-top:0;">
                {{ __(':km km included, then :rate per extra km.', ['km' => number_format($booking->included_km), 'rate' => \Modules\Pricing\Support\Money::dual($booking->extra_km_rate, $booking->extra_km_rate_eur)]) }}
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
            @if ($booking->isTransfer())
                <div class="admin-form-field">
                    <label for="start_date">{{ __('New pickup date') }}</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $booking->start_at->toDateString() }}" required>
                </div>
                <div class="admin-form-field">
                    <label for="transfer_time">{{ __('New pickup time (keeps the :minutes min trip)', ['minutes' => (int) $booking->start_at->diffInMinutes($booking->end_at)]) }}</label>
                    <input type="time" id="transfer_time" name="transfer_time" value="{{ $booking->start_at->format('H:i') }}" required>
                </div>
            @elseif ($booking->isHourly())
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
                <label for="start_date">{{ $booking->isStay() ? __('New check-in date') : ($booking->isActivity() ? __('New date') : __('New pickup date')) }}</label>
                <input type="date" id="start_date" name="start_date" value="{{ $booking->start_at->toDateString() }}" required>
            </div>
            <div class="admin-form-field">
                <label for="end_date">{{ $booking->isStay() ? __('New check-out date') : ($booking->isActivity() ? __('Same date again') : __('New return date')) }}</label>
                <input type="date" id="end_date" name="end_date" value="{{ $booking->checkOutDate()->toDateString() }}" required>
            </div>
            @endif
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Update dates') }}</button>
        </form>
    </div>

    @unless ($booking->hasNoUnit() || $vehicles->isEmpty())
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
    @endunless

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
