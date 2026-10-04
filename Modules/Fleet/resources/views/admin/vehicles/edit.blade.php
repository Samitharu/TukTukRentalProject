<x-admin::layouts.app :title="__('Edit unit')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.fleet.vehicles.update', $vehicle) }}" enctype="multipart/form-data" novalidate data-unit-form>
            @csrf
            @method('PUT')

            @include('fleet::admin.vehicles._form-fields')

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>

    @if ($vehicle->images->isNotEmpty())
        <div class="admin-card">
            <h2>{{ __('Photos') }}</h2>
            <div class="admin-image-grid">
                @foreach ($vehicle->images as $image)
                    <figure>
                        <img src="{{ asset('storage/'.$image->path) }}" alt="">
                        <form method="POST" action="{{ route('admin.fleet.vehicles.images.destroy', [$vehicle, $image]) }}" onsubmit="return confirm('{{ __('Remove this photo?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="position:absolute;top:2px;right:2px;background:#fff;border:1px solid #b3261e;color:#b3261e;border-radius:50%;width:24px;height:24px;cursor:pointer;">×</button>
                        </form>
                    </figure>
                @endforeach
            </div>
        </div>
    @endif

    <div class="admin-card">
        <h2>{{ $vehicle->isStay() ? __('Closures & maintenance') : __('Maintenance log') }}</h2>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('From') }}</th>
                    <th>{{ __('To') }}</th>
                    <th>{{ __('Cost') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->maintenanceLogs()->orderByDesc('starts_at')->get() as $log)
                    <tr>
                        <td>{{ $log->type }}</td>
                        <td>{{ $log->starts_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->ends_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->cost }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.fleet.vehicles.maintenance-logs.destroy', [$vehicle, $log]) }}" onsubmit="return confirm('{{ __('Remove this log entry?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b3261e;cursor:pointer;padding:0;">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.fleet.vehicles.maintenance-logs.store', $vehicle) }}" style="margin-top:1rem;" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="type">{{ __('Type') }}</label>
                <input type="text" id="type" name="type" placeholder="{{ __('e.g. Oil change, tyre replacement') }}" required>
            </div>
            <div class="admin-form-field">
                <label for="starts_at">{{ __('From') }}</label>
                <input type="datetime-local" id="starts_at" name="starts_at" required>
            </div>
            <div class="admin-form-field">
                <label for="ends_at">{{ __('To') }}</label>
                <input type="datetime-local" id="ends_at" name="ends_at" required>
            </div>
            <div class="admin-form-field">
                <label for="cost">{{ __('Cost') }}</label>
                <input type="number" id="cost" name="cost" step="0.01" min="0">
            </div>
            <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Add log entry') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
