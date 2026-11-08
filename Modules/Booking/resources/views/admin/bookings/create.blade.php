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
                        <option value="{{ $package->id }}" @selected(old('package_id') == $package->id)>{{ $package->name }} ({{ match (true) {
                            $package->isStay() => __('Stay'),
                            $package->isTransfer() => __('Airport pickup'),
                            $package->isHourly() => __('Rental, hourly :min–:max h', ['min' => $package->min_hours, 'max' => $package->max_hours ?? '…']),
                            default => __('Rental'),
                        } }})</option>
                    @endforeach
                </select>
            </div>

            @if ($vehicles->isNotEmpty())
                <div class="admin-form-field">
                    <label for="vehicle_id">{{ __('Specific unit (blank = auto-assign an eligible one)') }}</label>
                    <select id="vehicle_id" name="vehicle_id">
                        <option value="">{{ __('— Auto-assign —') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->adminLabel() }} ({{ $vehicle->isStay() ? __('Stay') : ($vehicle->isTransfer() ? __('Airport vehicle') : ($vehicle->category?->name ?? __('Rental'))) }})</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="admin-form-field">
                <label for="start_date">{{ __('Pickup date / check-in') }}</label>
                <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
            </div>

            <div class="admin-form-field">
                <label for="end_date">{{ __('Return date / check-out') }}</label>
                <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}">
                <p class="admin-hint">{{ __('For a stay package enter the check-out day — the guest is charged per night. The permit field below is ignored for stays. Leave empty for an airport pickup.') }}</p>
            </div>

            <fieldset class="admin-form-field admin-location">
                <legend>{{ __('Airport pickup packages only') }}</legend>
                <p class="admin-hint" style="margin-top:0;">{{ __('The pickup date is the date above.') }}</p>
                <div class="admin-location__coords">
                    <div>
                        <label for="transfer_direction">{{ __('Direction') }}</label>
                        <select id="transfer_direction" name="transfer_direction">
                            <option value="">{{ __('— Not an airport pickup —') }}</option>
                            <option value="arrival" @selected(old('transfer_direction') === 'arrival')>{{ __('Airport → address (arrival)') }}</option>
                            <option value="departure" @selected(old('transfer_direction') === 'departure')>{{ __('Address → airport (departure)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="transfer_time">{{ __('Pickup time') }}</label>
                        <input type="time" id="transfer_time" name="transfer_time" value="{{ old('transfer_time') }}">
                    </div>
                </div>
                <div class="admin-location__coords">
                    <div>
                        <label for="flight_number">{{ __('Flight number') }}</label>
                        <input type="text" id="flight_number" name="flight_number" value="{{ old('flight_number') }}" maxlength="10" placeholder="UL 504">
                    </div>
                    <div>
                        <label for="transfer_area_id">{{ __('Destination') }}</label>
                        <select id="transfer_area_id" name="transfer_area_id">
                            <option value="">{{ __(':area (standard trip, package price only)', ['area' => \Modules\Booking\Models\Booking::standardDestination()]) }}</option>
                            @foreach ($transferAreas as $area)
                                <option value="{{ $area->id }}" @selected(old('transfer_area_id') == $area->id)>{{ $area->name }} (+{{ $area->displayPrice() }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="admin-form-field">
                    <label for="transfer_address">{{ __('Hotel / address') }}</label>
                    <input type="text" id="transfer_address" name="transfer_address" value="{{ old('transfer_address') }}" maxlength="255">
                </div>
            </fieldset>

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
                <label for="people">{{ __('Number of people') }}</label>
                <input type="number" id="people" name="people" value="{{ old('people') }}" min="1" max="100" style="max-width:6rem;">
                <p class="admin-hint">{{ __('Only for packages priced by group size; the price comes from the matching group.') }}</p>
            </div>

            <p class="admin-hint">{{ __('Pickup') }}: {{ __('At our office') }}</p>

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
                        <label for="addon_{{ $addon->id }}">{{ $addon->name }} <small>({{ $addon->displayPrice() }} · {{ str_replace('_', ' ', $addon->pricing_unit) }}, {{ __('max :max', ['max' => $addon->max_quantity]) }})</small></label>
                        <input type="number" id="addon_{{ $addon->id }}" name="addons[{{ $addon->id }}]" value="{{ old('addons.'.$addon->id, 0) }}" min="0" max="{{ $addon->max_quantity }}" style="max-width:6rem;">
                    </div>
                @endforeach
            </div>
        @endif

        @if ($packageOffers->isNotEmpty())
            <div class="admin-card" data-offer-card>
                <h2 style="margin-top:0;">{{ __('Special offer') }}</h2>
                <p class="admin-hint" style="margin-top:0;">{{ __('Book a second package for this customer and save on the package above. Their details are used for both bookings.') }}</p>

                <div class="admin-form-field">
                    <label for="offer_id">{{ __('Add package') }}</label>
                    <select id="offer_id" name="offer_id" data-offer-select>
                        <option value="">{{ __('— No offer —') }}</option>
                        @foreach ($packageOffers as $offer)
                            <option value="{{ $offer->id }}" data-package="{{ $offer->package_id }}" data-fields="{{ match (true) {
                                $offer->offeredPackage->isTransfer() => 'transfer',
                                $offer->offeredPackage->isHourly() => 'hourly',
                                default => 'days',
                            } }}" @selected(old('offer_id') == $offer->id)>{{ __(':package — saves :saving', ['package' => $offer->offeredPackage->name, 'saving' => $offer->displayDiscount()]) }}</option>
                        @endforeach
                    </select>
                </div>

                <div data-offer-fields>
                    <div class="admin-location__coords">
                        <div class="admin-form-field">
                            <label for="offer_people">{{ __('Number of people') }}</label>
                            <input type="number" id="offer_people" name="offer[people]" value="{{ old('offer.people') }}" min="1" max="100" style="max-width:6rem;">
                            <p class="admin-hint">{{ __('Only for packages priced by group size.') }}</p>
                        </div>
                        <div class="admin-form-field">
                            <label for="offer_start_date">{{ __('Pickup date / check-in') }}</label>
                            <input type="date" id="offer_start_date" name="offer[start_date]" value="{{ old('offer.start_date') }}">
                        </div>
                        <div class="admin-form-field" data-offer-for="days">
                            <label for="offer_end_date">{{ __('Return date / check-out') }}</label>
                            <input type="date" id="offer_end_date" name="offer[end_date]" value="{{ old('offer.end_date') }}">
                        </div>
                    </div>

                    <div class="admin-location__coords" data-offer-for="hourly">
                        <div class="admin-form-field">
                            <label for="offer_start_time">{{ __('Start time') }}</label>
                            <select id="offer_start_time" name="offer[start_time]">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach ($hourlyStartTimes as $time)
                                    <option value="{{ $time }}" @selected(old('offer.start_time') === $time)>{{ $time }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label for="offer_hours">{{ __('Hours') }}</label>
                            <input type="number" id="offer_hours" name="offer[hours]" value="{{ old('offer.hours') }}" min="1" max="24">
                        </div>
                    </div>

                    <div data-offer-for="transfer">
                        <div class="admin-location__coords">
                            <div class="admin-form-field">
                                <label for="offer_transfer_direction">{{ __('Direction') }}</label>
                                <select id="offer_transfer_direction" name="offer[transfer_direction]">
                                    <option value="arrival" @selected(old('offer.transfer_direction') === 'arrival')>{{ __('Airport → address (arrival)') }}</option>
                                    <option value="departure" @selected(old('offer.transfer_direction') === 'departure')>{{ __('Address → airport (departure)') }}</option>
                                </select>
                            </div>
                            <div class="admin-form-field">
                                <label for="offer_transfer_time">{{ __('Pickup time') }}</label>
                                <input type="time" id="offer_transfer_time" name="offer[transfer_time]" value="{{ old('offer.transfer_time') }}">
                            </div>
                        </div>
                        <div class="admin-location__coords">
                            <div class="admin-form-field">
                                <label for="offer_flight_number">{{ __('Flight number') }}</label>
                                <input type="text" id="offer_flight_number" name="offer[flight_number]" value="{{ old('offer.flight_number') }}" maxlength="10" placeholder="UL 504">
                            </div>
                            <div class="admin-form-field">
                                <label for="offer_transfer_area_id">{{ __('Destination') }}</label>
                                <select id="offer_transfer_area_id" name="offer[transfer_area_id]">
                                    <option value="">{{ __(':area (standard trip, package price only)', ['area' => \Modules\Booking\Models\Booking::standardDestination()]) }}</option>
                                    @foreach ($transferAreas as $area)
                                        <option value="{{ $area->id }}" @selected(old('offer.transfer_area_id') == $area->id)>{{ $area->name }} (+{{ $area->displayPrice() }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-field">
                            <label for="offer_transfer_address">{{ __('Hotel / address') }}</label>
                            <input type="text" id="offer_transfer_address" name="offer[transfer_address]" value="{{ old('offer.transfer_address') }}" maxlength="255">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="admin-card">
            <div class="admin-form-field">
                <label for="special_requests">{{ __('Special requests') }}</label>
                <textarea id="special_requests" name="special_requests">{{ old('special_requests') }}</textarea>
            </div>

            <div class="admin-price-preview" data-price-preview aria-live="polite" data-labels="{{ json_encode([
                'rental' => __('Rental'),
                'seasonal' => __('Seasonal adjustment'),
                'addons' => __('Add-ons'),
                'transfer' => __('Destination area'),
                'delivery' => __('Delivery'),
                'offer_saving' => __('Offer saving'),
                'both_total' => __('Total for both bookings'),
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
