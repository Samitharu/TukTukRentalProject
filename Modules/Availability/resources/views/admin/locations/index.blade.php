<x-admin::layouts.app :title="__('Locations')">
    <p><a href="{{ route('admin.locations.create') }}" class="admin-btn">{{ __('Add location') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Address') }}</th>
                    <th>{{ __('Pickup point') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($locations as $location)
                    <tr>
                        <td>{{ $location->name }}</td>
                        <td>{{ $location->address }}</td>
                        <td>{{ $location->is_pickup_point ? __('Yes') : __('No') }}</td>
                        <td>{{ $location->is_active ? __('Yes') : __('No') }}</td>
                        <td>
                            <a href="{{ route('admin.locations.edit', $location) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.locations.destroy', $location) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this location?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0 0 0 0.5rem;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
