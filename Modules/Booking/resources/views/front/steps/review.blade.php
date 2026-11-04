@php
    $eur = $price->eur ?? [];
    $money = fn (float $usd, string $key) => \Modules\Pricing\Support\Money::dual($usd, $eur[$key] ?? null);
@endphp
<x-core::layouts.public :title="__('core::front.booking_review_title').' · '.config('app.name')">
    <x-slot:scripts>
        <script nonce="{{ csp_nonce() }}">
            // Double-submit guard: disable "Confirm" once the form really
            // submits (the browser's own required/checkbox validation has
            // passed). The server is idempotent anyway — see
            // BookingFlowController::confirm() — this just stops the
            // customer wondering whether the first click did anything.
            document.addEventListener('submit', function (event) {
                var form = event.target.closest('[data-confirm-booking]');
                if (!form) return;

                var button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = button.getAttribute('data-busy-text');
            });
        </script>
    </x-slot:scripts>

    <section class="section booking-flow">
        <div class="container" style="max-width:40rem;">
            @include('booking::front.steps._indicator', ['current' => 'review'])

            <h1>{{ __('core::front.booking_review_title') }}</h1>

            @error('booking')
                <div class="alert alert--warning" role="alert">{{ $message }}</div>
            @enderror
            <p class="text-muted">{{ __('core::front.booking_review_intro') }}</p>

            <div class="card" style="margin-bottom:var(--space-4);">
                <div class="card__body">
                    <h2 style="font-size:var(--font-size-base);">
                        {{ __('core::front.booking_review_dates') }}
                        <a href="{{ route('booking.start') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                    </h2>
                    @if ($isTransfer)
                        <p>{{ \Illuminate\Support\Carbon::parse($state['start_date'])->format('d M Y') }}, {{ $state['transfer_time'] }} &middot; {{ __($state['transfer_direction'] === 'departure' ? 'core::front.transfer_departure' : 'core::front.transfer_arrival') }}</p>
                        <p class="text-muted">{{ __('core::front.transfer_flight_number') }}: {{ $state['flight_number'] }} &middot; {{ $transferArea?->name }} &middot; {{ $state['transfer_address'] }}</p>
                    @elseif ($isStay)
                        @php
                            $checkIn = \Illuminate\Support\Carbon::parse($state['start_date']);
                            $checkOut = \Illuminate\Support\Carbon::parse($state['end_date'])->addDay();
                            $nights = (int) $checkIn->diffInDays($checkOut);
                        @endphp
                        <p>{{ __('core::front.booking_check_in') }}: {{ $checkIn->format('d M Y') }} &rarr; {{ __('core::front.booking_check_out') }}: {{ $checkOut->format('d M Y') }}</p>
                        <p class="text-muted">{{ trans_choice('core::front.booking_nights', $nights, ['count' => $nights]) }}</p>
                    @elseif ($hourlyRange)
                        <p>{{ $hourlyRange[0]->format('d M Y') }}, {{ $hourlyRange[0]->format('H:i') }}&ndash;{{ $hourlyRange[1]->format('H:i') }} ({{ trans_choice('core::front.booking_hours_count', (int) $state['hours'], ['count' => (int) $state['hours']]) }})</p>
                        <p class="text-muted">{{ $state['pickup_type'] === 'delivery' ? __('core::front.booking_pickup_delivery') : __('core::front.booking_pickup_office') }}</p>
                    @else
                        <p>{{ \Illuminate\Support\Carbon::parse($state['start_date'])->format('d M Y') }} &rarr; {{ \Illuminate\Support\Carbon::parse($state['end_date'])->format('d M Y') }}</p>
                        <p class="text-muted">{{ $state['pickup_type'] === 'delivery' ? __('core::front.booking_pickup_delivery') : __('core::front.booking_pickup_office') }}</p>
                    @endif

                    <h2 style="font-size:var(--font-size-base);">
                        {{ __('core::front.booking_review_package') }}
                        <a href="{{ route('booking.package') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                    </h2>
                    <p>{{ $package->name }}</p>
                    @unless ($isTransfer)
                        @include('package::front._km-allowance', ['package' => $package])
                    @endunless

                    @if (!empty($state['addons']))
                        <h2 style="font-size:var(--font-size-base);">
                            {{ __('core::front.booking_review_addons') }}
                            <a href="{{ route('booking.addons') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                        </h2>
                        @foreach ($price->addonLines as $line)
                            <p>{{ $line['name'] }} × {{ $line['quantity'] }}</p>
                        @endforeach
                    @endif

                    @if ($chosenOffer)
                        <h2 style="font-size:var(--font-size-base);">
                            {{ __('core::front.offer_added_heading') }}
                            <a href="{{ route('booking.offer', ['offer' => $chosenOffer->id]) }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                        </h2>
                        <p>{{ $chosenOffer->offeredPackage->name }}</p>
                        <p class="text-muted">
                            @if ($chosenOffer->offeredPackage->isTransfer())
                                {{ \Illuminate\Support\Carbon::parse($state['offer']['start_date'])->format('d M Y') }}, {{ $state['offer']['transfer_time'] }} &middot; {{ __($state['offer']['transfer_direction'] === 'departure' ? 'core::front.transfer_departure' : 'core::front.transfer_arrival') }} &middot; {{ $state['offer']['flight_number'] }} &middot; {{ $state['offer']['transfer_address'] }}
                            @elseif ($chosenOffer->offeredPackage->isHourly())
                                {{ \Illuminate\Support\Carbon::parse($state['offer']['start_date'])->format('d M Y') }}, {{ $state['offer']['start_time'] }} ({{ trans_choice('core::front.booking_hours_count', (int) $state['offer']['hours'], ['count' => (int) $state['offer']['hours']]) }})
                            @elseif ($chosenOffer->offeredPackage->isStay())
                                {{ __('core::front.booking_check_in') }}: {{ \Illuminate\Support\Carbon::parse($state['offer']['start_date'])->format('d M Y') }} &rarr; {{ __('core::front.booking_check_out') }}: {{ \Illuminate\Support\Carbon::parse($state['offer']['end_date'])->addDay()->format('d M Y') }}
                            @else
                                {{ \Illuminate\Support\Carbon::parse($state['offer']['start_date'])->format('d M Y') }} &rarr; {{ \Illuminate\Support\Carbon::parse($state['offer']['end_date'])->format('d M Y') }}
                            @endif
                        </p>
                    @endif

                    <h2 style="font-size:var(--font-size-base);">
                        {{ __($isStay || $isTransfer ? 'core::front.booking_review_guest' : 'core::front.booking_review_driver') }}
                        <a href="{{ route('booking.details') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                    </h2>
                    <p>{{ $state['first_name'] }} {{ $state['last_name'] }} &middot; {{ $state['email'] }} &middot; {{ $state['phone'] }}</p>
                </div>
            </div>

            <div class="price-breakdown" style="margin-bottom:var(--space-4);">
                <dl>
                    <dt>{{ __($isStay ? 'core::front.price_accommodation' : 'core::front.price_base_amount') }}</dt><dd>{{ $money($price->baseAmount, 'base_amount') }}</dd>

                    @if ($price->transferFee > 0)
                        <dt>{{ __('core::front.price_transfer_fee') }}</dt><dd>{{ $money($price->transferFee, 'transfer_fee') }}</dd>
                    @endif

                    @if ($price->seasonalAdjustment != 0)
                        <dt>{{ __('core::front.price_seasonal_adjustment') }}</dt><dd>{{ $money($price->seasonalAdjustment, 'seasonal_adjustment') }}</dd>
                    @endif

                    @if ($price->addonsTotal > 0)
                        <dt>{{ __('core::front.price_addons_total') }}</dt><dd>{{ $money($price->addonsTotal, 'addons_total') }}</dd>
                    @endif

                    @if ($price->deliveryFee > 0)
                        <dt>{{ __('core::front.price_delivery_fee') }}</dt><dd>{{ \Modules\Pricing\Support\Money::usd($price->deliveryFee) }}</dd>
                    @endif

                    <dt>{{ __('core::front.price_subtotal') }}</dt><dd>{{ $money($price->rentalSubtotal(), 'rental_subtotal') }}</dd>

                    @if ($price->offerDiscount > 0)
                        <dt>{{ __('core::front.offer_saving') }}</dt><dd>{{ \Modules\Pricing\Support\Money::dual(-$price->offerDiscount, isset($eur['offer_discount']) ? -$eur['offer_discount'] : null) }}</dd>
                    @endif

                    <div class="total-row" style="display:contents;">
                        <dt>{{ __('core::front.price_total') }}</dt><dd>{{ $money($price->total, 'total') }}</dd>
                    </div>

                    @if ($offeredPrice)
                        @php $bothEur = isset($eur['total'], $offeredPrice->eur['total']) ? $eur['total'] + $offeredPrice->eur['total'] : null; @endphp
                        <dt>{{ $chosenOffer->offeredPackage->name }}</dt><dd>{{ \Modules\Pricing\Support\Money::dual($offeredPrice->total, $offeredPrice->eur['total'] ?? null) }}</dd>

                        <div class="total-row" style="display:contents;">
                            <dt>{{ __('core::front.offer_total_both') }}</dt><dd>{{ \Modules\Pricing\Support\Money::dual($price->total + $offeredPrice->total, $bothEur) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($chosenOffer)
                <form method="POST" action="{{ route('booking.offer.remove') }}" style="margin-bottom:var(--space-4);">
                    @csrf
                    <button type="submit" class="btn btn--secondary">{{ __('core::front.offer_remove') }}</button>
                </form>
            @endif

            @foreach ($availableOffers as $offer)
                @php $fromTier = $offer->offeredPackage->pricingTiers->first(); @endphp
                <div class="card" style="margin-bottom:var(--space-4);">
                    <div class="card__body">
                        <h2 style="font-size:var(--font-size-base);">{{ __('core::front.offer_title') }}</h2>
                        <p>{{ __('core::front.offer_intro', ['package' => $offer->offeredPackage->name, 'saving' => $offer->displayDiscount(), 'main' => $package->name]) }}</p>
                        @if ($fromTier)
                            <p class="text-muted">{{ $offer->offeredPackage->name }}: {{ $fromTier->displayPrice() }} {{ __($offer->offeredPackage->priceSuffixKey()) }}</p>
                        @endif
                        <a href="{{ route('booking.offer', ['offer' => $offer->id]) }}" class="btn btn--primary">{{ __('core::front.offer_add') }}</a>
                    </div>
                </div>
            @endforeach

            <p class="text-muted">{{ __(match (true) {
                $isStay => 'core::front.booking_pay_on_arrival_notice',
                $isTransfer => 'core::front.booking_pay_driver_notice',
                default => 'core::front.booking_pay_on_pickup_notice',
            }) }}</p>

            <form method="POST" action="{{ route('booking.confirm') }}" data-confirm-booking>
                @csrf

                <div class="field">
                    <label class="check">
                        <input type="checkbox" name="terms_accepted" value="1" required>
                        {!! __('core::front.booking_terms_accept', [
                            'terms' => '<a href="'.route('pages.show', 'terms-conditions').'" target="_blank">'.__('core::front.nav_terms').'</a>',
                            'cancellation' => '<a href="'.route('pages.show', 'cancellation-policy').'" target="_blank">'.__('core::front.nav_cancellation').'</a>',
                        ]) !!}
                    </label>
                    @error('terms_accepted')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                @error('package_id')<div class="alert alert--error" role="alert">{{ $message }}</div>@enderror

                <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                    <a href="{{ route('booking.details') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
                    <button type="submit" class="btn btn--primary" style="flex:1;" data-busy-text="{{ __('core::front.booking_confirming') }}">{{ __('core::front.booking_confirm_button') }}</button>
                </div>
            </form>
        </div>
    </section>
</x-core::layouts.public>
