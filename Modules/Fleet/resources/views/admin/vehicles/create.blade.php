<x-admin::layouts.app :title="__('Add vehicle')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.fleet.vehicles.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <x-admin::translatable-field name="name" :label="__('Name')" :required="true" />
            <x-admin::translatable-field name="description" :label="__('Description')" :textarea="true" />

            <div class="admin-form-field">
                <label for="category_id">{{ __('Category') }}</label>
                <select id="category_id" name="category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="plate_no">{{ __('Plate number') }}</label>
                <input type="text" id="plate_no" name="plate_no" value="{{ old('plate_no') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="model">{{ __('Model') }}</label>
                <input type="text" id="model" name="model" value="{{ old('model') }}">
            </div>

            <div class="admin-form-field">
                <label for="year">{{ __('Year') }}</label>
                <input type="number" id="year" name="year" value="{{ old('year') }}" min="1990" max="{{ date('Y') + 1 }}">
            </div>

            <div class="admin-form-field">
                <label for="colour">{{ __('Colour') }}</label>
                <input type="text" id="colour" name="colour" value="{{ old('colour') }}">
            </div>

            <div class="admin-form-field">
                <label for="seats">{{ __('Seats') }}</label>
                <input type="number" id="seats" name="seats" value="{{ old('seats', 3) }}" min="1" max="6" required>
            </div>

            <div class="admin-form-field">
                <label for="transmission">{{ __('Transmission') }}</label>
                <select id="transmission" name="transmission" required>
                    <option value="manual" @selected(old('transmission', 'manual') === 'manual')>{{ __('Manual') }}</option>
                    <option value="automatic" @selected(old('transmission') === 'automatic')>{{ __('Automatic') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="fuel_type">{{ __('Fuel type') }}</label>
                <select id="fuel_type" name="fuel_type" required>
                    <option value="petrol" @selected(old('fuel_type', 'petrol') === 'petrol')>{{ __('Petrol') }}</option>
                    <option value="diesel" @selected(old('fuel_type') === 'diesel')>{{ __('Diesel') }}</option>
                    <option value="electric" @selected(old('fuel_type') === 'electric')>{{ __('Electric') }}</option>
                </select>
            </div>

            <fieldset class="admin-form-field">
                <legend style="font-weight:600;margin-bottom:0.5rem;">{{ __('Features') }}</legend>
                @foreach (['helmet_included' => __('Helmet included'), 'phone_holder' => __('Phone holder'), 'gps' => __('GPS'), 'bluetooth_speaker' => __('Bluetooth speaker'), 'storage_box' => __('Storage box'), 'sun_canopy' => __('Sun canopy')] as $value => $label)
                    <label style="display:block;font-weight:400;"><input type="checkbox" name="features[]" value="{{ $value }}"> {{ $label }}</label>
                @endforeach
            </fieldset>

            <div class="admin-form-field">
                <label for="status">{{ __('Status') }}</label>
                <select id="status" name="status" required>
                    <option value="active" @selected(old('status', 'active') === 'active')>{{ __('Active') }}</option>
                    <option value="maintenance" @selected(old('status') === 'maintenance')>{{ __('Maintenance') }}</option>
                    <option value="retired" @selected(old('status') === 'retired')>{{ __('Retired') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="images">{{ __('Photos') }}</label>
                <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
            </div>

            <button type="submit" class="admin-btn">{{ __('Add vehicle') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
