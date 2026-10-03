<x-admin::layouts.app :title="__('Edit vehicle')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.fleet.vehicles.update', $vehicle) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            <x-admin::translatable-field name="name" :label="__('Name')" :value="$vehicle->getTranslations('name')" :required="true" />
            <x-admin::translatable-field name="description" :label="__('Description')" :value="$vehicle->getTranslations('description')" :textarea="true" />

            <div class="admin-form-field">
                <label for="category_id">{{ __('Category') }}</label>
                <select id="category_id" name="category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $vehicle->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="plate_no">{{ __('Plate number') }}</label>
                <input type="text" id="plate_no" name="plate_no" value="{{ old('plate_no', $vehicle->plate_no) }}" required>
            </div>

            <div class="admin-form-field">
                <label for="model">{{ __('Model') }}</label>
                <input type="text" id="model" name="model" value="{{ old('model', $vehicle->model) }}">
            </div>

            <div class="admin-form-field">
                <label for="year">{{ __('Year') }}</label>
                <input type="number" id="year" name="year" value="{{ old('year', $vehicle->year) }}" min="1990" max="{{ date('Y') + 1 }}">
            </div>

            <div class="admin-form-field">
                <label for="colour">{{ __('Colour') }}</label>
                <input type="text" id="colour" name="colour" value="{{ old('colour', $vehicle->colour) }}">
            </div>

            <div class="admin-form-field">
                <label for="seats">{{ __('Seats') }}</label>
                <input type="number" id="seats" name="seats" value="{{ old('seats', $vehicle->seats) }}" min="1" max="6" required>
            </div>

            <div class="admin-form-field">
                <label for="transmission">{{ __('Transmission') }}</label>
                <select id="transmission" name="transmission" required>
                    <option value="manual" @selected(old('transmission', $vehicle->transmission) === 'manual')>{{ __('Manual') }}</option>
                    <option value="automatic" @selected(old('transmission', $vehicle->transmission) === 'automatic')>{{ __('Automatic') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="fuel_type">{{ __('Fuel type') }}</label>
                <select id="fuel_type" name="fuel_type" required>
                    <option value="petrol" @selected(old('fuel_type', $vehicle->fuel_type) === 'petrol')>{{ __('Petrol') }}</option>
                    <option value="diesel" @selected(old('fuel_type', $vehicle->fuel_type) === 'diesel')>{{ __('Diesel') }}</option>
                    <option value="electric" @selected(old('fuel_type', $vehicle->fuel_type) === 'electric')>{{ __('Electric') }}</option>
                </select>
            </div>

            <fieldset class="admin-form-field">
                <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Features') }}</legend>
                @php $currentFeatures = old('features', $vehicle->features ?? []); @endphp
                @foreach (['helmet_included' => __('Helmet included'), 'phone_holder' => __('Phone holder'), 'gps' => __('GPS'), 'bluetooth_speaker' => __('Bluetooth speaker'), 'storage_box' => __('Storage box'), 'sun_canopy' => __('Sun canopy')] as $value => $label)
                    <label style="display:block;font-weight:400;"><input type="checkbox" name="features[]" value="{{ $value }}" @checked(in_array($value, $currentFeatures, true))> {{ $label }}</label>
                @endforeach
            </fieldset>

            <div class="admin-form-field">
                <label for="status">{{ __('Status') }}</label>
                <select id="status" name="status" required>
                    <option value="active" @selected(old('status', $vehicle->status) === 'active')>{{ __('Active') }}</option>
                    <option value="maintenance" @selected(old('status', $vehicle->status) === 'maintenance')>{{ __('Maintenance') }}</option>
                    <option value="retired" @selected(old('status', $vehicle->status) === 'retired')>{{ __('Retired') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="images">{{ __('Add more photos') }}</label>
                <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
            </div>

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
        <h2>{{ __('Maintenance log') }}</h2>

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
