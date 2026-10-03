<x-admin::layouts.app :title="__('Blackout Dates')">
    <div class="admin-card">
        <h2>{{ __('Add blackout') }}</h2>
        <form method="POST" action="{{ route('admin.blackouts.store') }}" novalidate>
            @csrf

            <div class="admin-form-field">
                <label for="vehicle_id">{{ __('Vehicle (leave blank for business-wide)') }}</label>
                <select id="vehicle_id" name="vehicle_id">
                    <option value="">{{ __('— Whole business —') }}</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->plate_no }} — {{ $vehicle->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="starts_on">{{ __('From') }}</label>
                <input type="date" id="starts_on" name="starts_on" required>
            </div>

            <div class="admin-form-field">
                <label for="ends_on">{{ __('To') }}</label>
                <input type="date" id="ends_on" name="ends_on" required>
            </div>

            <div class="admin-form-field">
                <label for="reason">{{ __('Reason') }}</label>
                <input type="text" id="reason" name="reason" placeholder="{{ __('e.g. Public holiday, vehicle inspection') }}">
            </div>

            <button type="submit" class="admin-btn">{{ __('Add blackout') }}</button>
        </form>
    </div>

    <div class="admin-card" style="padding:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Vehicle') }}</th>
                    <th>{{ __('From') }}</th>
                    <th>{{ __('To') }}</th>
                    <th>{{ __('Reason') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($blackouts as $blackout)
                    <tr>
                        <td>{{ $blackout->vehicle?->plate_no ?? __('Whole business') }}</td>
                        <td>{{ $blackout->starts_on->format('Y-m-d') }}</td>
                        <td>{{ $blackout->ends_on->format('Y-m-d') }}</td>
                        <td>{{ $blackout->reason }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.blackouts.destroy', $blackout) }}" onsubmit="return confirm('{{ __('Remove this blackout?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin::layouts.app>
