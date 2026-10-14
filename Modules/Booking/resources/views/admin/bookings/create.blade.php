<x-admin::layouts.app :title="__('New manual booking')">
    <form method="POST" action="{{ route('admin.bookings.store') }}" novalidate
          data-manual-booking-form
          data-quote-url="{{ route('admin.bookings.quote') }}"
          data-lookup-url="{{ route('admin.bookings.customer-lookup') }}">
        @csrf

        <div class="admin-card">
            <h2 style="margin-top:0;">{{ __('Customer') }}</h2>
            <p class="admin-hint" style="margin-top:0;">{{ __('For walk-in, phone, or WhatsApp bookings made on a customer\'s behalf. Pick an existing customer, or fill in a new one.') }}</p>

            <input type="hidden" name="customer_id" value="{{ $selectedCustomer?->id }}" data-customer-id>

            <div class="admin-form-field" data-customer-search-field @if ($selectedCustomer) hidden @endif>
                <label for="customer_search">{{ __('Find existing customer') }}</label>
                <input type="search" id="customer_search" autocomplete="off" placeholder="{{ __('Name, email or phone…') }}" data-customer-search>
                <ul class="admin-picker" data-customer-results hidden></ul>
            </div>

            <div class="admin-alert admin-alert--success" data-customer-picked @unless ($selectedCustomer) hidden @endunless>
                {{ __('Booking for') }}: <strong data-customer-picked-label>{{ $selectedCustomer ? $selectedCustomer->full_name.' ('.$selectedCustomer->email.')' : '' }}</strong>
                <button type="button" class="admin-btn admin-btn--secondary" data-customer-clear style="margin-left:0.5rem;">{{ __('Change') }}</button>
            </div>

            <div class="admin-form-field">
                <label for="full_name">{{ __('Customer full name') }}</label>
                <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $selectedCustomer?->full_name) }}" required>
            </div>

            <div class="admin-form-field">
                <label for="email">{{ __('Customer email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $selectedCustomer?->email) }}" required @readonly($selectedCustomer)>
            </div>

            <div class="admin-form-field">
                <label for="phone">{{ __('Customer phone / WhatsApp') }}</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone', $selectedCustomer?->phone) }}">
            </div>

            <div class="admin-form-field">
                <label for="nationality">{{ __('Nationality') }}</label>
                <select id="nationality" name="nationality">
                    <option value="">{{ __('— Not given —') }}</option>
                    @foreach ($countries as $code => $name)
                        <option value="{{ $code }}" @selected(old('nationality', $selectedCustomer?->nationality) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="passport_number">{{ __('Passport number') }}</label>
                <input type="text" id="passport_number" name="passport_number" value="{{ old('passport_number') }}" maxlength="40" autocomplete="off">
                <p class="admin-hint">{{ __('Optional. Stored encrypted. An existing customer\'s saved details are never overwritten — only missing ones are filled in.') }}</p>
            </div>
        </div>

        <div class="admin-card">
            <h2 style="margin-top:0;">{{ __('Rental') }}</h2>
            <p class="admin-hint" style="margin-top:0;">{{ __('Price is always recalculated server-side from the selected package and add-ons — never trust a total entered here.') }}</p>

            <div class="admin-form-field">
                <label for="package_id">{{ __('Package') }}</label>
                <select id="package_id" name="package_id" required data-package-select>
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected(old('package_id') == $package->id)>{{ $package->name }} ({{ $package->isStay() ? __('Stay') : ($package->isHourly() ? __('Tuk tuk, hourly :min–:max h', ['min' => $package->min_hours, 'max' => $package->max_hours ?? '…']) : __('Tuk tuk')) }})</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="vehicle_id">{{ __('Specific unit (blank = auto-assign an eligible one)') }}</label>
                <select id="vehicle_id" name="vehicle_id">
                    <option value="">{{ __('— Auto-assign —') }}</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->adminLabel() }} ({{ $vehicle->isStay() ? __('Stay') : __('Tuk tuk') }})</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="start_date">{{ __('Pickup date / check-in') }}</label>
                <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="end_date">{{ __('Return date / check-out') }}</label>
                <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                <p class="admin-hint">{{ __('For a stay package enter the check-out day — the guest is charged per night. Pickup and permit fields below are ignored for stays.') }}</p>
            </div>

            <fieldset class="admin-form-field admin-location">
                <legend>{{ __('Hourly packages only') }}</legend>
                <p class="admin-hint" style="margin-top:0;">{{ __('Use the same date for pickup and return. The tuk tuk is blocked for the whole day.') }}</p>
                <div class="admin-location__coords">
                    <div>
                        <label for="start_time">{{ __('Start time') }}</label>
                        <select id="start_time" name="start_time">
                            <option value="">{{ __('— Not hourly —') }}</option>
                            @foreach ($hourlyStartTimes as $time)
                                <option value="{{ $time }}" @selected(old('start_time') === $time)>{{ $time }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="hours">{{ __('Hours') }}</label>
                        <input type="number" id="hours" name="hours" value="{{ old('hours') }}" min="1" max="24">
                    </div>
                </div>
            </fieldset>

            <div class="admin-form-field">
                <label for="pickup_type">{{ __('Pickup type') }}</label>
                <select id="pickup_type" name="pickup_type" required>
                    <option value="office" @selected(old('pickup_type', 'office') === 'office')>{{ __('At our office') }}</option>
                    <option value="delivery" @selected(old('pickup_type') === 'delivery')>{{ __('Delivery') }}</option>
                </select>
            </div>

            <div class="admin-form-field">
                <label for="business_location_id">{{ __('Pickup location') }}</label>
                <select id="business_location_id" name="business_location_id">
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('business_location_id') == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label for="delivery_zone_id">{{ __('Delivery zone (if delivery)') }}</label>
                <select id="delivery_zone_id" name="delivery_zone_id">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($deliveryZones as $zone)
                        <option value="{{ $zone->id }}" @selected(old('delivery_zone_id') == $zone->id)>{{ $zone->name }} (+{{ $zone->extra_fee }})</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-form-field">
                <label><input type="hidden" name="has_international_permit" value="0"><input type="checkbox" name="has_international_permit" value="1" @checked(old('has_international_permit'))> {{ __('Has International Driving Permit') }}</label>
            </div>
        </div>

        @if ($addons->isNotEmpty())
            <div class="admin-card">
                <h2 style="margin-top:0;">{{ __('Add-ons') }}</h2>
                <p class="admin-hint" style="margin-top:0;" data-addons-empty hidden>{{ __('The selected package offers no extra add-ons.') }}</p>
                @foreach ($addons as $addon)
                    <div class="admin-form-field admin-addon-row" data-addon-packages="{{ implode(',', $addonPackageIds[$addon->id] ?? []) }}">
                        <label for="addon_{{ $addon->id }}">{{ $addon->name }} <small>({{ $addon->price }} · {{ str_replace('_', ' ', $addon->pricing_unit) }}, {{ __('max :max', ['max' => $addon->max_quantity]) }})</small></label>
                        <input type="number" id="addon_{{ $addon->id }}" name="addons[{{ $addon->id }}]" value="{{ old('addons.'.$addon->id, 0) }}" min="0" max="{{ $addon->max_quantity }}" style="max-width:6rem;">
                    </div>
                @endforeach
            </div>
        @endif

        <div class="admin-card">
            <div class="admin-form-field">
                <label for="coupon_code">{{ __('Coupon code (optional)') }}</label>
                <input type="text" id="coupon_code" name="coupon_code" value="{{ old('coupon_code') }}">
            </div>

            <div class="admin-form-field">
                <label for="special_requests">{{ __('Special requests') }}</label>
                <textarea id="special_requests" name="special_requests">{{ old('special_requests') }}</textarea>
            </div>

            <div class="admin-price-preview" data-price-preview aria-live="polite" data-labels="{{ json_encode([
                'rental' => __('Rental'),
                'seasonal' => __('Seasonal adjustment'),
                'addons' => __('Add-ons'),
                'delivery' => __('Delivery'),
                'coupon' => __('Coupon discount'),
                'couponIgnored' => __('Coupon not valid — not applied.'),
                'total' => __('Total'),
                'deposit' => __('Deposit'),
                'free' => __(':free of :total eligible units free for these dates.'),
                'none' => __('No eligible unit is free for these dates.'),
                'loading' => __('Calculating…'),
                'error' => __('Could not calculate the price.'),
            ]) }}">
                <p class="admin-hint" style="margin:0;">{{ __('Choose a package and dates to see the price and availability.') }}</p>
            </div>

            <div class="admin-form-field">
                <label><input type="hidden" name="notify_customer" value="0"><input type="checkbox" name="notify_customer" value="1" @checked(old('notify_customer', '1') === '1')> {{ __('Email the customer a booking confirmation') }}</label>
            </div>

            <button type="submit" class="admin-btn">{{ __('Create booking') }}</button>
        </div>
    </form>

    <script src="{{ asset_v('assets/js/admin-booking-form.js') }}" defer nonce="{{ csp_nonce() }}"></script>
</x-admin::layouts.app>
