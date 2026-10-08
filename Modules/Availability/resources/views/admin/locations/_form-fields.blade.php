{{-- Shared by create/edit; $location is null on create. --}}
<x-admin::translatable-field name="name" :label="__('Name')" :value="$location?->getTranslations('name') ?? []" :required="true" />

<fieldset
    class="admin-form-field admin-location"
    data-location-picker
    data-msg-placed="{{ __('Pin placed from the link — drag it if it is not exactly right.') }}"
    data-msg-short="{{ __('Short link detected — the exact spot will be read from it when you save.') }}"
    data-msg-unreadable="{{ __('No coordinates in this link. That is fine — it will still be saved; drop the pin on the map as well.') }}"
>
    <legend>{{ __('Location') }}</legend>

    <div class="admin-form-field">
        <label for="google_maps_url">{{ __('Google Maps link') }}</label>
        <input type="url" id="google_maps_url" name="google_maps_url" value="{{ old('google_maps_url', $location?->google_maps_url) }}" placeholder="https://maps.app.goo.gl/…" inputmode="url" data-location-link>
        <p class="admin-hint">{{ __('In Google Maps, open the place, tap Share → Copy link, and paste it here.') }}</p>
        <p class="admin-location__status" data-location-status aria-live="polite"></p>
    </div>

    <div class="admin-form-field">
        <label for="address">{{ __('Address') }}</label>
        <input type="text" id="address" name="address" value="{{ old('address', $location?->address) }}" maxlength="500">
    </div>

    <div class="admin-location__map" data-location-map role="application" aria-label="{{ __('Map — click to place the pin, or drag it') }}"></div>
    <p class="admin-hint">{{ __('Click the map or drag the pin to fine-tune the exact spot.') }}</p>

    <div class="admin-location__coords">
        <div>
            <label for="lat">{{ __('Latitude') }}</label>
            <input type="text" id="lat" name="lat" value="{{ old('lat', $location?->lat) }}" inputmode="decimal" data-location-lat>
        </div>
        <div>
            <label for="lng">{{ __('Longitude') }}</label>
            <input type="text" id="lng" name="lng" value="{{ old('lng', $location?->lng) }}" inputmode="decimal" data-location-lng>
        </div>
    </div>
</fieldset>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_pickup_point" value="1" @checked(old('is_pickup_point', $location?->is_pickup_point ?? true))> {{ __('Customers can pick up / return here') }}</label>
</div>

<div class="admin-form-field">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location?->is_active ?? true))> {{ __('Active') }}</label>
</div>

<x-core::map-assets />
