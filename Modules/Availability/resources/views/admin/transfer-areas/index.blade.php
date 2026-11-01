<x-admin::layouts.app :title="__('Airport Pickup Areas')">
    <p class="admin-hint">{{ __('Where customers can be picked up from or taken to. Each area has its own trip price (added to the package price) and how long the vehicle is away — that time is blocked for other airport bookings on the same vehicle.') }}</p>
    <p><a href="{{ route('admin.transfer-areas.create') }}" class="admin-btn">{{ __('Add area') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Price') }}</th>
                    <th>{{ __('Trip duration') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($areas as $area)
                    <tr>
                        <td>{{ $area->name }}</td>
                        <td>{{ number_format((float) $area->price, 2) }} {{ config('pricing.default_currency') }}</td>
                        <td>{{ __(':minutes min', ['minutes' => $area->duration_minutes]) }}</td>
                        <td>{{ $area->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.transfer-areas.edit', $area) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.transfer-areas.destroy', $area) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this area?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0 0 0 0.5rem;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('No areas yet — add one so customers can book an airport pickup.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
