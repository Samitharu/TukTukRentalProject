{{-- Where a public booking came from, and the other upcoming bookings that
     share its customer / IP / phone / passport (SuspiciousBookingDetector) —
     so staff can clear out a batch of spam bookings in one go. --}}
<div class="admin-card">
    <h2>{{ __('Booking source') }}</h2>
    @if ($booking->ip_address === null)
        <p class="admin-hint" style="margin-top:0;">{{ __('No IP recorded — made by staff, or before source tracking was added.') }}</p>
    @else
        <p><strong>{{ __('IP address') }}:</strong> <code>{{ $booking->ip_address }}</code>
            @if ($ipBlock)
                <span class="admin-badge admin-badge--warn">{{ __('Blocked until :date', ['date' => $ipBlock->blocked_until?->format('Y-m-d') ?? __('further notice')]) }}</span>
            @endif
        </p>
        @if ($booking->user_agent)
            <p><strong>{{ __('Browser') }}:</strong> <small>{{ $booking->user_agent }}</small></p>
        @endif

        @if ($ipBlock)
            <form method="POST" action="{{ route('admin.bookings.unblock-ip', $booking) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Unblock IP') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.bookings.block-ip', $booking) }}" onsubmit="return confirm('{{ __('Block this IP from booking online for :days days? Existing bookings are not cancelled.', ['days' => config('booking.block_ip_days')]) }}');">
                @csrf
                <button type="submit" class="admin-btn admin-btn--danger">{{ __('Block IP from booking online') }}</button>
                <span class="admin-hint">{{ __('Blocked visitors are asked to contact you instead. Mobile networks share IPs, so the block expires after :days days.', ['days' => config('booking.block_ip_days')]) }}</span>
            </form>
        @endif
    @endif
</div>

@if ($related->isNotEmpty())
    <div class="admin-card" style="padding:0;">
        <form method="POST" action="{{ route('admin.bookings.cancel-related', $booking) }}" onsubmit="return confirm('{{ __('Cancel all ticked bookings?') }}');">
            @csrf
            <div style="padding:var(--admin-space-3) var(--admin-space-3) 0;">
                <h2 style="margin-top:0;">{{ __('Related upcoming bookings (:count)', ['count' => $related->count()]) }}</h2>
                <p class="admin-hint" style="margin-top:0;">{{ __('Not yet picked up, and sharing something with this booking. Untick any that look genuine.') }}</p>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th>{{ __('Dates') }}</th>
                        <th>{{ __('Booked') }}</th>
                        <th>{{ __('Matches on') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($canCancelThis)
                        <tr>
                            <td><input type="checkbox" name="booking_ids[]" value="{{ $booking->id }}" checked aria-label="{{ $booking->reference }}"></td>
                            <td><code>{{ $booking->reference }}</code> <small>({{ __('this booking') }})</small></td>
                            <td>{{ $booking->customer->full_name }}</td>
                            <td>{{ $booking->vehicle->adminLabel() }}</td>
                            <td>{{ $booking->start_at->format('Y-m-d') }} → {{ $booking->checkOutDate()->format('Y-m-d') }}</td>
                            <td>{{ $booking->created_at?->format('Y-m-d H:i') }}</td>
                            <td>—</td>
                        </tr>
                    @endif
                    @foreach ($related as $other)
                        <tr>
                            <td><input type="checkbox" name="booking_ids[]" value="{{ $other->id }}" checked aria-label="{{ $other->reference }}"></td>
                            <td><a href="{{ route('admin.bookings.show', $other) }}"><code>{{ $other->reference }}</code></a></td>
                            <td>{{ $other->customer->full_name }}<br><small>{{ $other->customer->email }}</small></td>
                            <td>{{ $other->vehicle->adminLabel() }}</td>
                            <td>{{ $other->start_at->format('Y-m-d') }} → {{ $other->checkOutDate()->format('Y-m-d') }}</td>
                            <td>{{ $other->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ implode(', ', array_map('__', $other->matched_signals)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding:var(--admin-space-3);">
                <div class="admin-form-field">
                    <label for="bulk_reason">{{ __('Reason') }}</label>
                    <input type="text" id="bulk_reason" name="reason" value="{{ old('reason', __('Spam booking')) }}" maxlength="500" required>
                </div>
                <button type="submit" class="admin-btn admin-btn--danger">{{ __('Cancel ticked bookings') }}</button>
            </div>
        </form>
    </div>
@endif
