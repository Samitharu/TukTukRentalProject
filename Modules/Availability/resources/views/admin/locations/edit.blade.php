<x-admin::layouts.app :title="__('Edit location')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.locations.update', $location) }}" novalidate>
            @csrf
            @method('PUT')

            <x-admin::translatable-field name="name" :label="__('Name')" :value="$location->getTranslations('name')" :required="true" />

            <div class="admin-form-field">
                <label for="address">{{ __('Address') }}</label>
                <input type="text" id="address" name="address" value="{{ old('address', $location->address) }}">
            </div>

            <div class="admin-form-field">
                <label for="lat">{{ __('Latitude') }}</label>
                <input type="text" id="lat" name="lat" value="{{ old('lat', $location->lat) }}">
            </div>

            <div class="admin-form-field">
                <label for="lng">{{ __('Longitude') }}</label>
                <input type="text" id="lng" name="lng" value="{{ old('lng', $location->lng) }}">
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="is_pickup_point" value="1" @checked(old('is_pickup_point', $location->is_pickup_point))> {{ __('Customers can pick up / return here') }}</label>
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active))> {{ __('Active') }}</label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
