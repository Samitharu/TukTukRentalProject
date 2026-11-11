@php
    $value = fn (string $field) => old('offer.'.$field, $values[$field] ?? '');
    // A stay's state holds its last night; the form shows the check-out day.
    $endValue = old('offer.end_date', $offered->isStay() && ! empty($values['end_date']) ? \Illuminate\Support\Carbon::parse($values['end_date'])->addDay()->toDateString() : ($values['end_date'] ?? ''));
    $direction = $value('transfer_direction') ?: 'arrival';
    $title = __('core::front.offer_page_title', ['package' => $offered->name]);
@endphp
<x-core::layouts.public :title="$title.' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:40rem;">
            <h1>{{ $title }}</h1>
            @include('package::front._gallery-trigger', ['package' => $offered, 'class' => 'package-gallery__banner'])
            @if ($offer)
                <p class="offer-flag">{{ __('core::front.offer_page_intro', ['saving' => $offer->displayDiscount(), 'main' => $offer->package->name]) }}</p>
            @elseif ($savingOnAdded)
                <p class="offer-flag">{{ __('core::front.offer_page_intro', ['saving' => $savingOnAdded->displayDiscount(), 'main' => $offered->name]) }}</p>
            @else
                <p class="text-muted">{{ __('core::front.package_add_page_intro') }}</p>
            @endif

            <form class="booking-panel" method="POST" action="{{ $offer ? route('booking.offer.store', ['offer' => $offer->id]) : route('booking.add.store', ['package' => $offered->id]) }}" novalidate>
                @csrf

                @if ($offered->isGroupPriced())
                    <div class="field">
                        <label for="people">{{ __('core::front.booking_people') }}</label>
                        <select id="people" name="offer[people]" required>
                            @foreach ($offered->groupSizes() as $size)
                                <option value="{{ $size }}" @selected((int) $value('people') === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                        @error('offer.people')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($offered->requires_licence_documents)
                    <div class="field">
                        <label for="drivers">{{ __('core::front.booking_drivers') }}</label>
                        <select id="drivers" name="offer[drivers]" required style="max-width:10rem;">
                            @for ($count = 1; $count <= \Modules\Booking\Models\Booking::MAX_DRIVERS; $count++)
                                <option value="{{ $count }}" @selected((int) ($value('drivers') ?: 1) === $count)>{{ $count }}</option>
                            @endfor
                        </select>
                        <p class="text-muted" style="font-size:var(--font-size-sm);margin:0.35rem 0 0;">
                            {{ $offered->displayLicenceFee()
                                ? __('core::front.booking_drivers_hint_fee', ['price' => $offered->displayLicenceFee()])
                                : __('core::front.booking_drivers_hint') }}
                        </p>
                        @error('offer.drivers')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($offered->isTransfer())
                    <fieldset class="field choice-group">
                        <legend>{{ __('core::front.transfer_direction') }}</legend>
                        <div class="choice-group__options">
                            <label class="choice">
                                <input type="radio" name="offer[transfer_direction]" value="arrival" @checked($direction === 'arrival')>
                                <span class="choice__label">{{ __('core::front.transfer_arrival') }}</span>
                            </label>
                            <label class="choice">
                                <input type="radio" name="offer[transfer_direction]" value="departure" @checked($direction === 'departure')>
                                <span class="choice__label">{{ __('core::front.transfer_departure') }}</span>
                            </label>
                        </div>
                        @error('offer.transfer_direction')<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="grid grid--2">
                        <div class="field">
                            <label for="start_date">{{ __('core::front.transfer_date') }}</label>
                            <input type="date" id="start_date" name="offer[start_date]" min="{{ now()->toDateString() }}" value="{{ $value('start_date') }}" required>
                            @error('offer.start_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label for="transfer_time">{{ __('core::front.transfer_time') }}</label>
                            <input type="time" id="transfer_time" name="offer[transfer_time]" value="{{ $value('transfer_time') }}" required>
                            <p class="text-muted" style="font-size:var(--font-size-sm);margin:0.25rem 0 0;">{{ __('core::front.transfer_time_hint') }}</p>
                            @error('offer.transfer_time')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="field">
                        <label for="flight_number">{{ __('core::front.transfer_flight_number') }}</label>
                        <input type="text" id="flight_number" name="offer[flight_number]" value="{{ $value('flight_number') }}" placeholder="UL 504" maxlength="10" autocapitalize="characters" required>
                        @error('offer.flight_number')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="transfer_area_id">{{ __('core::front.transfer_area_optional') }}</label>
                        <select id="transfer_area_id" name="offer[transfer_area_id]" aria-describedby="transfer_area_hint">
                            <option value="">{{ __('core::front.transfer_area_none', ['city' => config('core.business.city')]) }}</option>
                            @foreach ($transferAreas as $area)
                                <option value="{{ $area->id }}" @selected((string) $value('transfer_area_id') === (string) $area->id)>{{ __('core::front.transfer_area_option', ['area' => $area->name, 'price' => $area->displayPrice()]) }}</option>
                            @endforeach
                        </select>
                        <p class="text-muted" id="transfer_area_hint" style="font-size:var(--font-size-sm);margin:0.35rem 0 0;">{{ __('core::front.transfer_area_hint', ['city' => config('core.business.city')]) }}</p>
                        @error('offer.transfer_area_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="transfer_address">{{ $direction === 'departure' ? __('core::front.transfer_address_pickup') : __('core::front.transfer_address') }}</label>
                        <input type="text" id="transfer_address" name="offer[transfer_address]" value="{{ $value('transfer_address') }}" maxlength="255" placeholder="{{ __('core::front.transfer_address_placeholder') }}" required>
                        @error('offer.transfer_address')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @elseif ($offered->isActivity())
                    <div class="field">
                        <label for="start_date">{{ __('core::front.booking_activity_date') }}</label>
                        <input type="date" id="start_date" name="offer[start_date]" min="{{ now()->toDateString() }}" value="{{ $value('start_date') }}" required>
                        @error('offer.start_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    @if ($offered->pricing_model === \Modules\Package\Models\Package::MODEL_PER_PERSON)
                        <div class="field">
                            <label for="people">{{ __('core::front.booking_people') }}</label>
                            <input type="number" id="people" name="offer[people]" min="1" max="50" value="{{ $value('people') ?: 1 }}" required>
                            @error('offer.people')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <p class="text-muted">{{ __('core::front.booking_activity_note') }}</p>
                @elseif ($offered->isHourly())
                    <div class="field">
                        <label for="start_date">{{ __('core::front.booking_start_date') }}</label>
                        <input type="date" id="start_date" name="offer[start_date]" min="{{ now()->toDateString() }}" value="{{ $value('start_date') }}" required>
                        @error('offer.start_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid--2">
                        <div class="field">
                            <label for="start_time">{{ __('core::front.booking_start_time') }}</label>
                            <select id="start_time" name="offer[start_time]" required>
                                @foreach ($hourlyStartTimes as $time)
                                    <option value="{{ $time }}" @selected($value('start_time') === $time)>{{ $time }}</option>
                                @endforeach
                            </select>
                            @error('offer.start_time')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label for="hours">{{ __('core::front.booking_hours') }}</label>
                            <select id="hours" name="offer[hours]" required>
                                @foreach ($hourlyLengths as $length)
                                    <option value="{{ $length }}" @selected((string) $value('hours') === (string) $length)>{{ trans_choice('core::front.booking_hours_count', $length, ['count' => $length]) }}</option>
                                @endforeach
                            </select>
                            @error('offer.hours')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @else
                    <div class="grid grid--2">
                        <div class="field">
                            <label for="start_date">{{ __($offered->isStay() ? 'core::front.booking_check_in' : 'core::front.booking_start_date') }}</label>
                            <input type="date" id="start_date" name="offer[start_date]" min="{{ now()->toDateString() }}" value="{{ $value('start_date') }}" required>
                            @error('offer.start_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label for="end_date">{{ __($offered->isStay() ? 'core::front.booking_check_out' : 'core::front.booking_end_date') }}</label>
                            <input type="date" id="end_date" name="offer[end_date]" min="{{ now()->toDateString() }}" value="{{ $endValue }}" required>
                            @error('offer.end_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endif

                @if ($offered->kind === \Modules\Package\Models\Package::KIND_VEHICLE)
                    <p class="text-muted">{{ __('core::front.booking_pickup_office') }}</p>
                @endif

                @if ($asksLicence)
                    <div class="field">
                        <label class="check">
                            <input type="hidden" name="offer[has_valid_licence]" value="0">
                            <input type="checkbox" name="offer[has_valid_licence]" value="1" @checked(old('offer.has_valid_licence') === '1') required>
                            {{ __('core::front.booking_has_valid_licence') }}
                        </label>
                        @error('offer.has_valid_licence')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label class="check">
                            <input type="hidden" name="offer[has_international_permit]" value="0">
                            <input type="checkbox" name="offer[has_international_permit]" value="1" @checked((string) old('offer.has_international_permit', ! empty($values['has_international_permit']) ? '1' : '0') === '1')>
                            {{ __('core::front.booking_has_international_permit') }}
                        </label>
                        @error('offer.has_international_permit')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                    <a href="{{ route('booking.review') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
                    <button type="submit" class="btn btn--primary" style="flex:1;">{{ __('core::front.offer_add') }}</button>
                </div>
            </form>
        </div>
    </section>
</x-core::layouts.public>
