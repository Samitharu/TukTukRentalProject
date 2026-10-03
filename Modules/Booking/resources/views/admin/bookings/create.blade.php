<x-admin::layouts.app :title="__('New manual booking')">
    <div class="admin-card">
        <p style="color:#5b6b64;">{{ __('For walk-in, phone, or WhatsApp bookings. Price is always recalculated server-side from the selected package and add-ons — never trust a total entered here.') }}</p>

        <form method="POST" action="{{ route('admin.bookings.store') }}" novalidate>
            @csrf

            <div class="admin-form-field">
                <label for="package_id">{{ __('Package') }}</label>
                <select id="package_id" name="package_id" required>
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected(old('package_id') == $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="vehicle_id">{{ __('Specific vehicle (blank = auto-assign an eligible one)') }}</label>
                <select id="vehicle_id" name="vehicle_id">
                    <option value="">{{ __('— Auto-assign —') }}</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->plate_no }} — {{ $vehicle->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="start_date">{{ __('Pickup date') }}</label>
                <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="end_date">{{ __('Return date') }}</label>
                <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="pickup_type">{{ __('Pickup type') }}</label>
                <select id="pickup_type" name="pickup_type" required>
                    <option value="office">{{ __('At our office') }}</option>
                    <option value="delivery">{{ __('Delivery') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="business_location_id">{{ __('Pickup location') }}</label>
                <select id="business_location_id" name="business_location_id">
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="delivery_zone_id">{{ __('Delivery zone (if delivery)') }}</label>
                <select id="delivery_zone_id" name="delivery_zone_id">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($deliveryZones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }} (+{{ $zone->extra_fee }})</option>
                    @endforeach
                </select>
            </div>

            <hr>

            <div class="admin-form-field">
                <label for="full_name">{{ __('Customer full name') }}</label>
                <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="email">{{ __('Customer email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="phone">{{ __('Customer phone') }}</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
            </div>

            <div class="admin-form-field">
                <label for="nationality">{{ __('Nationality (ISO 2-letter, e.g. DE)') }}</label>
                <input type="text" id="nationality" name="nationality" value="{{ old('nationality') }}" maxlength="2">
            </div>

            <div class="admin-form-field">
                <label for="coupon_code">{{ __('Coupon code (optional)') }}</label>
                <input type="text" id="coupon_code" name="coupon_code" value="{{ old('coupon_code') }}">
            </div>

            <div class="admin-form-field">
                <label><input type="checkbox" name="has_international_permit" value="1" @checked(old('has_international_permit'))> {{ __('Has International Driving Permit') }}</label>
            </div>

            <div class="admin-form-field">
                <label for="special_requests">{{ __('Special requests') }}</label>
                <textarea id="special_requests" name="special_requests">{{ old('special_requests') }}</textarea>
            </div>

            <button type="submit" class="admin-btn">{{ __('Create booking') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
