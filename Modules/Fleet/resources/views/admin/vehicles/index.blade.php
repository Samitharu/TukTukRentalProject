<x-admin::layouts.app :title="__('Fleet')">
    <p><a href="{{ route('admin.fleet.vehicles.create') }}" class="admin-btn">{{ __('Add vehicle') }}</a></p>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ __('Plate No.') }}</th>
                    <th>{{ __('Name') }}</th>
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
                        <td><code>{{ $vehicle->plate_no }}</code></td>
                        <td>{{ $vehicle->name }}</td>
                        <td>{{ $vehicle->category->name }}</td>
                        <td>{{ ucfirst($vehicle->status) }}</td>
                        <td>
                            <a href="{{ route('admin.fleet.vehicles.edit', $vehicle) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.fleet.vehicles.destroy', $vehicle) }}" style="display:inline;" onsubmit="return confirm('{{ __('Remove this vehicle?') }}');">
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
