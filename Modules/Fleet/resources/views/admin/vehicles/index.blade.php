<x-admin::layouts.app :title="__('Units')">
    <p><a href="{{ route('admin.fleet.vehicles.create') }}" class="admin-btn">{{ __('Add unit') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Plate No. / Location') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicles as $vehicle)
                    <tr>
                        <td>
                            @if ($vehicle->primaryImage())
                                <img src="{{ asset('storage/'.$vehicle->primaryImage()->path) }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">
                            @endif
                        </td>
                        <td>{{ $vehicle->name }}</td>
                        <td>
                            @if ($vehicle->isStay())
                                <span class="admin-badge admin-badge--stay">{{ __('Stay') }}</span>
                            @else
                                <span class="admin-badge">{{ __('Tuk tuk') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($vehicle->plate_no)
                                <code>{{ $vehicle->plate_no }}</code>
                            @endif
                            @if ($vehicle->mapUrl())
                                <a href="{{ $vehicle->mapUrl() }}" target="_blank" rel="noopener noreferrer">{{ $vehicle->address ?: __('View on Google Maps') }}</a>
                            @elseif ($vehicle->isStay())
                                <span style="color:#a94338;">{{ __('No location yet') }}</span>
                            @endif
                        </td>
                        <td>{{ $vehicle->category->name }}</td>
                        <td>{{ ucfirst($vehicle->status) }}</td>
                        <td>
                            <a href="{{ route('admin.fleet.vehicles.edit', $vehicle) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.fleet.vehicles.destroy', $vehicle) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this unit?') }}');">
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

    {{ $vehicles->links() }}
</x-admin::layouts.app>
