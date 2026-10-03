<x-core::layouts.public :title="__('core::front.booking_dates_title').' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:40rem;">
            @include('booking::front.steps._indicator', ['current' => 'dates'])

            <h1>{{ __('core::front.booking_dates_title') }}</h1>
            <p class="text-muted">{{ __('core::front.booking_dates_intro') }}</p>

            <form
                class="booking-panel"
                method="POST"
                action="{{ route('booking.start.store') }}"
                novalidate
                x-data="{ pickupType: '{{ old('pickup_type', $state['pickup_type'] ?? 'office') }}' }"
            >
                @csrf

                <div class="grid grid--2">
                    <div class="field">
                        <label for="start_date">{{ __('core::front.booking_start_date') }}</label>
                        <input type="date" id="start_date" name="start_date" min="{{ now()->toDateString() }}" value="{{ old('start_date', $state['start_date'] ?? '') }}" required>
                        @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="end_date">{{ __('core::front.booking_end_date') }}</label>
                        <input type="date" id="end_date" name="end_date" min="{{ now()->toDateString() }}" value="{{ old('end_date', $state['end_date'] ?? '') }}" required>
                        @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <fieldset class="field choice-group">
                    <legend>{{ __('core::front.booking_pickup_type') }}</legend>
                    <div class="choice-group__options">
                        <label class="choice">
                            <input type="radio" name="pickup_type" value="office" x-model="pickupType" @checked(old('pickup_type', $state['pickup_type'] ?? 'office') === 'office')>
                            <span class="choice__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 11.5 12 4l8 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10v8.5a1 1 0 0 0 1 1h3v-5h4v5h3a1 1 0 0 0 1-1V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="choice__label">{{ __('core::front.booking_pickup_office') }}</span>
                        </label>
                        <label class="choice">
                            <input type="radio" name="pickup_type" value="delivery" x-model="pickupType" @checked(old('pickup_type', $state['pickup_type'] ?? 'office') === 'delivery')>
                            <span class="choice__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.4" stroke="currentColor" stroke-width="1.8"/></svg></span>
                            <span class="choice__label">{{ __('core::front.booking_pickup_delivery') }}</span>
                        </label>
                    </div>
                    @error('pickup_type')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>

                <div class="field" x-show="pickupType === 'office'" x-cloak>
                    <label for="business_location_id">{{ __('core::front.booking_pickup_location') }}</label>
                    <select id="business_location_id" name="business_location_id">
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) old('business_location_id', $state['business_location_id'] ?? '') === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('business_location_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field" x-show="pickupType === 'delivery'" x-cloak>
                    <label for="delivery_zone_id">{{ __('core::front.booking_delivery_zone') }}</label>
                    <select id="delivery_zone_id" name="delivery_zone_id">
                        @foreach ($deliveryZones as $zone)
                            <option value="{{ $zone->id }}" @selected((string) old('delivery_zone_id', $state['delivery_zone_id'] ?? '') === (string) $zone->id)>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    @error('delivery_zone_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn--primary btn--block">{{ __('core::front.booking_continue') }}</button>
            </form>
        </div>
    </section>
</x-core::layouts.public>
