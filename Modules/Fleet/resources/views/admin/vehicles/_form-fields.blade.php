@php
    $vehicle = $vehicle ?? null;
    $selectedCategoryId = (string) old('category_id', $vehicle?->category_id ?? $categories->first()?->id);
    $selectedKind = $categories->firstWhere('id', (int) $selectedCategoryId)?->kind ?? 'vehicle';
    $currentFeatures = old('features', $vehicle?->features ?? []);
    $featureLabels = [
        'helmet_included' => __('Helmet included'), 'phone_holder' => __('Phone holder'), 'gps' => __('GPS'),
        'bluetooth_speaker' => __('Bluetooth speaker'), 'storage_box' => __('Storage box'), 'sun_canopy' => __('Sun canopy'),
        'wifi' => __('Wi-Fi'), 'air_conditioning' => __('Air conditioning'), 'hot_water' => __('Hot water'),
        'private_bathroom' => __('Private bathroom'), 'sea_view' => __('Sea view'), 'breakfast_included' => __('Breakfast included'),
        'kitchen' => __('Kitchen'), 'parking' => __('Parking'),
    ];
@endphp

<x-admin::translatable-field name="name" :label="__('Name')" :value="$vehicle?->getTranslations('name') ?? []" :required="true" />
<x-admin::translatable-field name="description" :label="__('Description')" :value="$vehicle?->getTranslations('description') ?? []" :textarea="true" />

<div class="admin-form-field">
    <label for="category_id">{{ __('Category') }}</label>
    <select id="category_id" name="category_id" required data-unit-category>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" data-kind="{{ $category->kind }}" @selected($selectedCategoryId === (string) $category->id)>
                {{ $category->name }} ({{ $category->isStay() ? __('Stay') : __('Tuk tuk') }})
            </option>
        @endforeach
    </select>
    <p class="admin-hint">{{ __('Cabanas and rooms go in a category of type "Stay" — create one under Unit Categories first.') }}</p>
</div>

<div data-unit-kind="vehicle" @if ($selectedKind !== 'vehicle') hidden @endif>
    <div class="admin-form-field">
        <label for="plate_no">{{ __('Plate number') }}</label>
        <input type="text" id="plate_no" name="plate_no" value="{{ old('plate_no', $vehicle?->plate_no) }}" required>
    </div>

    <div class="admin-form-field">
        <label for="model">{{ __('Model') }}</label>
        <input type="text" id="model" name="model" value="{{ old('model', $vehicle?->model) }}">
    </div>

    <div class="admin-form-field">
        <label for="year">{{ __('Year') }}</label>
        <input type="number" id="year" name="year" value="{{ old('year', $vehicle?->year) }}" min="1990" max="{{ date('Y') + 1 }}">
    </div>

    <div class="admin-form-field">
        <label for="colour">{{ __('Colour') }}</label>
        <input type="text" id="colour" name="colour" value="{{ old('colour', $vehicle?->colour) }}">
    </div>

    <div class="admin-form-field">
        <label for="transmission">{{ __('Transmission') }}</label>
        <select id="transmission" name="transmission" required>
            <option value="manual" @selected(old('transmission', $vehicle?->transmission ?? 'manual') === 'manual')>{{ __('Manual') }}</option>
            <option value="automatic" @selected(old('transmission', $vehicle?->transmission) === 'automatic')>{{ __('Automatic') }}</option>
        </select>
    </div>

    <div class="admin-form-field">
        <label for="fuel_type">{{ __('Fuel type') }}</label>
        <select id="fuel_type" name="fuel_type" required>
            <option value="petrol" @selected(old('fuel_type', $vehicle?->fuel_type ?? 'petrol') === 'petrol')>{{ __('Petrol') }}</option>
            <option value="diesel" @selected(old('fuel_type', $vehicle?->fuel_type) === 'diesel')>{{ __('Diesel') }}</option>
            <option value="electric" @selected(old('fuel_type', $vehicle?->fuel_type) === 'electric')>{{ __('Electric') }}</option>
        </select>
    </div>
</div>

<div class="admin-form-field">
    <label for="seats">
        <span data-unit-kind="vehicle" @if ($selectedKind !== 'vehicle') hidden @endif>{{ __('Seats') }}</span>
        <span data-unit-kind="stay" @if ($selectedKind !== 'stay') hidden @endif>{{ __('Maximum guests') }}</span>
    </label>
    <input type="number" id="seats" name="seats" value="{{ old('seats', $vehicle?->seats ?? 3) }}" min="1" max="30" required>
</div>

@foreach ($features as $kind => $options)
    <fieldset class="admin-form-field" data-unit-kind="{{ $kind }}" @if ($selectedKind !== $kind) hidden @endif>
        <legend style="font-weight:600;margin-bottom:0.5rem;">{{ $kind === 'stay' ? __('Amenities') : __('Features') }}</legend>
        @foreach ($options as $value)
            <label style="display:block;font-weight:400;"><input type="checkbox" name="features[]" value="{{ $value }}" @checked(in_array($value, $currentFeatures, true))> {{ $featureLabels[$value] ?? $value }}</label>
        @endforeach
    </fieldset>
@endforeach

<fieldset
    class="admin-form-field admin-location"
    data-location-picker
    data-msg-placed="{{ __('Pin placed from the link — drag it if it is not exactly right.') }}"
    data-msg-short="{{ __('Short link detected — the exact spot will be read from it when you save.') }}"
    data-msg-unreadable="{{ __('No coordinates in this link. That is fine — it will still be saved; drop the pin on the map as well.') }}"
>
    <legend>{{ __('Location') }}</legend>
    <p class="admin-hint" data-unit-kind="stay" @if ($selectedKind !== 'stay') hidden @endif>{{ __('Required for cabanas and rooms — customers see it on the website with a "Get directions" button.') }}</p>
    <p class="admin-hint" data-unit-kind="vehicle" @if ($selectedKind !== 'vehicle') hidden @endif>{{ __('Optional for tuk tuks — where it is normally parked.') }}</p>

    <div class="admin-form-field">
        <label for="google_maps_url">{{ __('Google Maps link') }}</label>
        <input type="url" id="google_maps_url" name="google_maps_url" value="{{ old('google_maps_url', $vehicle?->google_maps_url) }}" placeholder="https://maps.app.goo.gl/…" inputmode="url" data-location-link>
        <p class="admin-hint">{{ __('In Google Maps, open the place, tap Share → Copy link, and paste it here.') }}</p>
        <p class="admin-location__status" data-location-status aria-live="polite"></p>
    </div>

    <div class="admin-form-field">
        <label for="address">{{ __('Address (optional)') }}</label>
        <input type="text" id="address" name="address" value="{{ old('address', $vehicle?->address) }}" maxlength="500">
    </div>

    <div class="admin-location__map" data-location-map role="application" aria-label="{{ __('Map — click to place the pin, or drag it') }}"></div>
    <p class="admin-hint">{{ __('Click the map or drag the pin to fine-tune the exact spot.') }}</p>

    <div class="admin-location__coords">
        <div>
            <label for="lat">{{ __('Latitude') }}</label>
            <input type="text" id="lat" name="lat" value="{{ old('lat', $vehicle?->lat) }}" inputmode="decimal" data-location-lat>
        </div>
        <div>
            <label for="lng">{{ __('Longitude') }}</label>
            <input type="text" id="lng" name="lng" value="{{ old('lng', $vehicle?->lng) }}" inputmode="decimal" data-location-lng>
        </div>
    </div>
</fieldset>

<div class="admin-form-field">
    <label for="status">{{ __('Status') }}</label>
    <select id="status" name="status" required>
        <option value="active" @selected(old('status', $vehicle?->status ?? 'active') === 'active')>{{ __('Active') }}</option>
        <option value="maintenance" @selected(old('status', $vehicle?->status) === 'maintenance')>{{ __('Maintenance / closed') }}</option>
        <option value="retired" @selected(old('status', $vehicle?->status) === 'retired')>{{ __('Retired') }}</option>
    </select>
</div>

<div class="admin-form-field">
    <label for="images">{{ $vehicle ? __('Add more photos') : __('Photos') }}</label>
    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
</div>

<x-core::map-assets />
<script src="{{ asset_v('assets/js/admin-unit-form.js') }}" defer nonce="{{ csp_nonce() }}"></script>
