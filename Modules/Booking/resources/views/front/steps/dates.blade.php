@php
    // A stay's state holds its last night; the form shows the check-out day.
    $storedEnd = $state['end_date'] ?? null;
    $endValue = $isStay && $storedEnd ? \Illuminate\Support\Carbon::parse($storedEnd)->addDay()->toDateString() : $storedEnd;
    $titleKey = match (true) {
        $isStay => 'core::front.booking_stay_dates_title',
        $isTransfer => 'core::front.booking_transfer_title',
        default => 'core::front.booking_dates_title',
    };
    $direction = old('transfer_direction', $state['transfer_direction'] ?? 'arrival');
@endphp
<x-core::layouts.public :title="__($titleKey).' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:40rem;">
            @include('booking::front.steps._indicator', ['current' => 'dates'])

            @if ($lines->count() > 1)
                <div class="booking-kind-switch">
                    @foreach ($lines as $line)
                        <a href="{{ route('booking.start', ['line' => $line->id]) }}" class="btn btn--secondary" @if ($currentLineId === $line->id) aria-current="true" @endif>{{ $line->name }}</a>
                    @endforeach
                </div>
            @endif

            <h1>{{ __($titleKey) }}</h1>
            <p class="text-muted">{{ __(match (true) {
                $isStay => 'core::front.booking_stay_dates_intro',
                $isTransfer => 'core::front.booking_transfer_intro',
                default => 'core::front.booking_dates_intro',
            }, ['city' => config('core.business.city')]) }}</p>

            <form
                class="booking-panel"
                method="POST"
                action="{{ route('booking.start.store') }}"
                novalidate
            >
                @csrf

                @if ($isTransfer)
                    <fieldset class="field choice-group">
                        <legend>{{ __('core::front.transfer_direction') }}</legend>
                        <div class="choice-group__options">
                            <label class="choice">
                                <input type="radio" name="transfer_direction" value="arrival" @checked($direction === 'arrival')>
                                <span class="choice__label">{{ __('core::front.transfer_arrival') }}</span>
                            </label>
                            <label class="choice">
                                <input type="radio" name="transfer_direction" value="departure" @checked($direction === 'departure')>
                                <span class="choice__label">{{ __('core::front.transfer_departure') }}</span>
                            </label>
                        </div>
                        @error('transfer_direction')<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="grid grid--2">
                        <div class="field">
                            <label for="start_date">{{ __('core::front.transfer_date') }}</label>
                            <input type="date" id="start_date" name="start_date" min="{{ now()->toDateString() }}" value="{{ old('start_date', $state['start_date'] ?? '') }}" required>
                            @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label for="transfer_time">{{ __('core::front.transfer_time') }}</label>
                            <input type="time" id="transfer_time" name="transfer_time" value="{{ old('transfer_time', $state['transfer_time'] ?? '') }}" required>
                            <p class="text-muted" style="font-size:var(--font-size-sm);margin:0.25rem 0 0;">{{ __('core::front.transfer_time_hint') }}</p>
                            @error('transfer_time')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="field">
                        <label for="flight_number">{{ __('core::front.transfer_flight_number') }}</label>
                        <input type="text" id="flight_number" name="flight_number" value="{{ old('flight_number', $state['flight_number'] ?? '') }}" placeholder="UL 504" maxlength="10" autocapitalize="characters" required>
                        @error('flight_number')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="transfer_area_id">{{ __('core::front.transfer_area_optional') }}</label>
                        <select id="transfer_area_id" name="transfer_area_id" aria-describedby="transfer_area_hint">
                            <option value="">{{ __('core::front.transfer_area_none', ['city' => config('core.business.city')]) }}</option>
                            @foreach ($transferAreas as $area)
                                <option value="{{ $area->id }}" @selected((string) old('transfer_area_id', $state['transfer_area_id'] ?? '') === (string) $area->id)>{{ __('core::front.transfer_area_option', ['area' => $area->name, 'price' => $area->displayPrice()]) }}</option>
                            @endforeach
                        </select>
                        <p class="text-muted" id="transfer_area_hint" style="font-size:var(--font-size-sm);margin:0.35rem 0 0;">{{ __('core::front.transfer_area_hint', ['city' => config('core.business.city')]) }}</p>
                        @error('transfer_area_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="transfer_address">{{ $direction === 'departure' ? __('core::front.transfer_address_pickup') : __('core::front.transfer_address') }}</label>
                        <input type="text" id="transfer_address" name="transfer_address" value="{{ old('transfer_address', $state['transfer_address'] ?? '') }}" maxlength="255" placeholder="{{ __('core::front.transfer_address_placeholder') }}" required>
                        @error('transfer_address')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @else
                    <div class="grid grid--2">
                        <div class="field">
                            <label for="start_date">{{ __($isStay ? 'core::front.booking_check_in' : 'core::front.booking_start_date') }}</label>
                            <input type="date" id="start_date" name="start_date" min="{{ now()->toDateString() }}" value="{{ old('start_date', $state['start_date'] ?? '') }}" required>
                            @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label for="end_date">{{ __($isStay ? 'core::front.booking_check_out' : 'core::front.booking_end_date') }}</label>
                            <input type="date" id="end_date" name="end_date" min="{{ $isStay ? now()->addDay()->toDateString() : now()->toDateString() }}" value="{{ old('end_date', $endValue ?? '') }}" required>
                            @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if (! $isStay)
                        {{-- Rentals are only collected at the office — no pickup choice. --}}
                        <p class="text-muted">{{ __('core::front.booking_pickup_office') }}</p>
                    @endif
                @endif

                <button type="submit" class="btn btn--primary btn--block">{{ __('core::front.booking_continue') }}</button>
            </form>
        </div>
    </section>
</x-core::layouts.public>
