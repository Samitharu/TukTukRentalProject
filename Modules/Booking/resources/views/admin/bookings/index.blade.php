<x-admin::layouts.app :title="__('Bookings')">
    <p><a href="{{ route('admin.bookings.create') }}" class="admin-btn">{{ __('New manual booking') }}</a></p>

    <form method="GET" class="admin-card">
        <div class="admin-form-field" style="display:inline-block;margin-right:1rem;">
            <label for="q">{{ __('Search reference / customer') }}</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}">
        </div>
        <div class="admin-form-field" style="display:inline-block;">
            <label for="status">{{ __('Status') }}</label>
            <select id="status" name="status">
                <option value="">{{ __('All') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Filter') }}</button>
    </form>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ __('Customer') }}</th>
                    <th>{{ __('Vehicle') }}</th>
                    <th>{{ __('Pickup') }}</th>
                    <th>{{ __('Return') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $booking) }}"><code>{{ $booking->reference }}</code></a></td>
                        <td>{{ $booking->customer->full_name }}</td>
                        <td>{{ $booking->vehicle->plate_no }}</td>
                        <td>{{ $booking->start_at->format('Y-m-d') }}</td>
                        <td>{{ $booking->end_at->format('Y-m-d') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</td>
                        <td>{{ $booking->total_amount }} {{ $booking->currency_code }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">{{ __('No bookings found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $bookings->links() }}
</x-admin::layouts.app>
