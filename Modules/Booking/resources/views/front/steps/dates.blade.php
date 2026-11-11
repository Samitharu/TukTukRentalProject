@php
    // A stay's state holds its last night; the form shows the check-out day.
    $storedEnd = $state['end_date'] ?? null;
    $endValue = $isStay && $storedEnd ? \Illuminate\Support\Carbon::parse($storedEnd)->addDay()->toDateString() : $storedEnd;
    $isTour ??= false;
    $titleKey = match (true) {
        $isStay => 'core::front.booking_stay_dates_title',
        $isTransfer => 'core::front.booking_transfer_title',
        $isTour => 'core::front.booking_tour_dates_title',
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
                $isTour => 'core::front.booking_tour_dates_intro',
                default => 'core::front.booking_dates_intro',
            }, ['city' => config('core.business.city')]) }}</p>

            @if ($linePackages->isEmpty())
                {{-- Nothing to book in this service yet: no point asking for dates. --}}
                <div class="booking-panel booking-empty" role="status">
                    <span class="booking-empty__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="7" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 7V5.5a1.5 1.5 0 0 1 1.5-1.5h4a1.5 1.5 0 0 1 1.5 1.5V7M3.5 12h17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                    <h2>{{ $currentLine ? __('core::front.booking_line_empty_title', ['line' => $currentLine->name]) : __('core::front.booking_line_empty_title_generic') }}</h2>
                    <p class="text-muted">{{ __($lines->count() > 1 ? 'core::front.booking_line_empty_body' : 'core::front.booking_line_empty_body_single') }}</p>
                    <a href="{{ route('contact') }}" class="btn btn--primary">{{ __('core::front.contact_title') }}</a>
                </div>
            @else
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
                            <label for="start_date">{{ __(match (true) {
                                $isStay => 'core::front.booking_check_in',
                                $isTour => 'core::front.booking_tour_start_date',
                                default => 'core::front.booking_start_date',
                            }) }}</label>
                            <input type="date" id="start_date" name="start_date" min="{{ now()->toDateString() }}" value="{{ old('start_date', $state['start_date'] ?? '') }}" required>
                            @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label for="end_date">{{ __(match (true) {
                                $isStay => 'core::front.booking_check_out',
                                $isTour => 'core::front.booking_tour_end_date',
                                default => 'core::front.booking_end_date',
                            }) }}</label>
                            <input type="date" id="end_date" name="end_date" min="{{ $isStay ? now()->addDay()->toDateString() : now()->toDateString() }}" value="{{ old('end_date', $endValue ?? '') }}" required>
                            @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if (! $isStay && ! $isTour)
                        {{-- Rentals are only collected at the office — no pickup choice. --}}
                        <p class="text-muted">{{ __('core::front.booking_pickup_office') }}</p>
                    @endif
                @endif

                <button type="submit" class="btn btn--primary btn--block">{{ __('core::front.booking_continue') }}</button>
            </form>

            <section class="line-preview" aria-labelledby="line-preview-title">
                <h2 id="line-preview-title">{{ $currentLine ? __('core::front.booking_line_preview_title', ['line' => $currentLine->name]) : __('core::front.booking_line_preview_title_generic') }}</h2>
                <p class="text-muted">{{ $currentLine?->description ?: __('core::front.booking_line_preview_intro') }}</p>

                <ul class="line-preview__list">
                    @foreach ($linePackages as $package)
                        @php $cover = $package->primaryImage(); @endphp
                        <li class="line-preview__item">
                            @if ($cover)
                                <img src="{{ $cover->thumbUrl() }}" alt="" loading="lazy" class="line-preview__photo">
                            @else
                                <span class="line-preview__photo line-preview__photo--empty" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v12H4zM4 11h16M9 4v3M15 4v3" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/></svg>
                                </span>
                            @endif
                            <div class="line-preview__body">
                                <h3>{{ $package->name }}</h3>
                                @if (filled($package->description))
                                    <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $package->description), 90) }}</p>
                                @endif
                                <p class="line-preview__meta">
                                    @if ($package->pricingTiers->isNotEmpty())
                                        <span>@include('package::front._from-price', ['package' => $package])</span>
                                    @endif
                                    @if (! $package->isHourly() && ! $package->isActivity() && $package->min_days > 1)
                                        <span class="line-preview__tag">{{ __($package->isStay() ? 'core::front.packages_min_nights' : 'core::front.packages_min_days', ['count' => $package->min_days]) }}</span>
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="line-preview__note text-muted">{{ __('core::front.booking_line_preview_note') }}</p>
            </section>
            @endif
        </div>
    </section>
</x-core::layouts.public>
