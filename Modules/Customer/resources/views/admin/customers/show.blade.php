<x-admin::layouts.app :title="$customer->full_name">
    <div class="admin-card">
        <p><strong>{{ __('Email') }}:</strong> {{ $customer->email }}</p>
        <p><strong>{{ __('Phone') }}:</strong> {{ $customer->phone }}</p>
        <p><strong>{{ __('Nationality') }}:</strong> {{ $customer->nationality }}</p>
        <p><strong>{{ __('Customer since') }}:</strong> {{ $customer->created_at->format('Y-m-d') }}</p>
    </div>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Pickup') }}</th>
                    <th>{{ __('Return') }}</th>
                    <th>{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customer->bookings as $booking)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $booking) }}"><code>{{ $booking->reference }}</code></a></td>
                        <td>{{ $booking->status }}</td>
                        <td>{{ $booking->start_at->format('Y-m-d') }}</td>
                        <td>{{ $booking->end_at->format('Y-m-d') }}</td>
                        <td>{{ $booking->total_amount }} {{ $booking->currency_code }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('No bookings yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
