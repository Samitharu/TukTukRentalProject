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

            @foreach ($availableOffers as $offer)
                @php
                    $offered = $offer->offeredPackage;
                    $fromTier = $offered->pricingTiers->first();
                    $withOffer = $offerPrices[$offer->id];
                    $saving = \Modules\Pricing\Support\Money::dual($withOffer->offerDiscount, $withOffer->eur['offer_discount'] ?? null);
                @endphp
                <section class="offer-card">
                    <span class="offer-card__tag">{{ __('core::front.offer_title') }}</span>
                    <h2 class="offer-card__headline">{{ __('core::front.offer_headline', ['saving' => $saving, 'main' => $package->name]) }}</h2>
                    <p class="offer-card__lead">{{ __('core::front.offer_lead', ['package' => $offered->name, 'main' => $package->name]) }}</p>

                    <div class="offer-card__deal">
                        @include('package::front._gallery-trigger', ['package' => $offered, 'class' => 'gallery-trigger--small', 'photoClass' => 'offer-card__photo', 'thumb' => true, 'compact' => true])
                        <dl class="offer-card__prices">
                            <dt>{{ $package->name }}</dt>
                            <dd>
                                <s>{{ $money($price->total, 'total') }}</s>
                                <strong>{{ \Modules\Pricing\Support\Money::dual($withOffer->total, $withOffer->eur['total'] ?? null) }}</strong>
                            </dd>
                            @if ($fromTier)
                                <dt>{{ $offered->name }}</dt>
                                <dd>{{ __('core::front.packages_from') }} {{ $fromTier->displayPrice() }} {{ __($offered->priceSuffixKey()) }}</dd>
                            @endif
                        </dl>
                    </div>

                    <a href="{{ route('booking.offer', ['offer' => $offer->id]) }}" class="btn btn--accent btn--block offer-card__cta">{{ __('core::front.offer_cta', ['package' => $offered->name, 'saving' => $saving]) }}</a>
                    <p class="offer-card__note">{{ __('core::front.offer_no_details') }}</p>
                </section>
            @endforeach

            @foreach ($offersOnOthers as $offer)
                @php
                    $cheaper = $offer->package;
                    $fromTier = $cheaper->pricingTiers->first();
                @endphp
                <section class="offer-card">
                    <span class="offer-card__tag">{{ __('core::front.offer_title') }}</span>
                    <h2 class="offer-card__headline">{{ __('core::front.offer_headline', ['saving' => $offer->displayDiscount(), 'main' => $cheaper->name]) }}</h2>
                    <p class="offer-card__lead">{{ __('core::front.offer_lead_added', ['package' => $cheaper->name, 'main' => $package->name]) }}</p>

                    <div class="offer-card__deal">
                        @include('package::front._gallery-trigger', ['package' => $cheaper, 'class' => 'gallery-trigger--small', 'photoClass' => 'offer-card__photo', 'thumb' => true, 'compact' => true])
                        @if ($fromTier)
                            <dl class="offer-card__prices">
                                <dt>{{ $cheaper->name }}</dt>
                                <dd>{{ __('core::front.packages_from') }} {{ $fromTier->displayPrice() }} {{ __($cheaper->priceSuffixKey()) }}</dd>
                            </dl>
                        @endif
                    </div>

                    <a href="{{ route('booking.add', ['package' => $cheaper->id]) }}" class="btn btn--accent btn--block offer-card__cta">{{ __('core::front.offer_cta', ['package' => $cheaper->name, 'saving' => $offer->displayDiscount()]) }}</a>
                    <p class="offer-card__note">{{ __('core::front.offer_no_details') }}</p>
                </section>
            @endforeach

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
                        @include('booking::front.steps._added-package-period', ['added' => $chosenOffer->offeredPackage, 'data' => $state['offer']])
                    @endif

                    @foreach ($addedPackages as $added)
                        <h2 style="font-size:var(--font-size-base);">
                            {{ __('core::front.package_added_heading') }}
                            <a href="{{ route('booking.add', ['package' => $added['package']->id]) }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                        </h2>
                        <p>{{ $added['package']->name }}</p>
                        @include('booking::front.steps._added-package-period', ['added' => $added['package'], 'data' => $added['data']])
                        <form method="POST" action="{{ route('booking.add.remove', ['package' => $added['package']->id]) }}">
                            @csrf
                            <button type="submit" class="added-package__remove">{{ __('core::front.package_added_remove') }}</button>
                        </form>
                    @endforeach

                    <h2 style="font-size:var(--font-size-base);">
                        {{ __($isStay || $isTransfer ? 'core::front.booking_review_guest' : 'core::front.booking_review_driver') }}
                        <a href="{{ route('booking.details') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                    </h2>
                    <p>{{ $state['first_name'] }} {{ $state['last_name'] }} &middot; {{ $state['email'] }} &middot; {{ $state['phone'] }}</p>
                </div>
            </div>

            <div class="price-breakdown" style="margin-bottom:var(--space-4);">
                <dl>
                    <dt>{{ __(match (true) {
                        $isStay => 'core::front.price_accommodation',
                        $isTransfer => 'core::front.price_airport_pickup',
                        default => 'core::front.price_base_amount',
                    }) }}</dt><dd>{{ $money($price->baseAmount, 'base_amount') }}</dd>

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
                        <dt class="offer-saving">{{ __('core::front.offer_saving') }}</dt><dd class="offer-saving">{{ \Modules\Pricing\Support\Money::dual(-$price->offerDiscount, isset($eur['offer_discount']) ? -$eur['offer_discount'] : null) }}</dd>
                    @endif

                    <div class="total-row" style="display:contents;">
                        <dt>{{ __('core::front.price_total') }}</dt><dd>{{ $money($price->total, 'total') }}</dd>
                    </div>

                    @if ($offeredPrice)
                        <dt>{{ $chosenOffer->offeredPackage->name }}</dt><dd>{{ \Modules\Pricing\Support\Money::dual($offeredPrice->total, $offeredPrice->eur['total'] ?? null) }}</dd>
                    @endif

                    @foreach ($addedPackages as $added)
                        @if ($added['price']->offerDiscount > 0)
                            <dt class="offer-saving">{{ __('core::front.offer_saving') }}</dt><dd class="offer-saving">{{ \Modules\Pricing\Support\Money::dual(-$added['price']->offerDiscount, isset($added['price']->eur['offer_discount']) ? -$added['price']->eur['offer_discount'] : null) }}</dd>
                        @endif
                        <dt>{{ $added['package']->name }}</dt><dd>{{ \Modules\Pricing\Support\Money::dual($added['price']->total, $added['price']->eur['total'] ?? null) }}</dd>
                    @endforeach

                    @if ($allBookingsTotal)
                        <div class="total-row" style="display:contents;">
                            <dt>{{ __('core::front.offer_total_all') }}</dt><dd>{{ $allBookingsTotal }}</dd>
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

            @if ($addablePackages->isNotEmpty())
                <details class="add-package" open>
                    <summary>
                        <span aria-hidden="true" class="add-package__plus">+</span>
                        <span>{{ __('core::front.package_add_heading') }}</span>
                    </summary>
                    <p class="add-package__intro">{{ __('core::front.package_add_intro') }}</p>
                    @foreach ($addablePackages as $line => $linePackages)
                        <h3 class="add-package__line">{{ $line }}</h3>
                        @foreach ($linePackages as $addable)
                        <div class="add-package__row">
                            @include('package::front._gallery-trigger', ['package' => $addable, 'class' => 'gallery-trigger--small', 'photoClass' => 'add-package__photo', 'thumb' => true, 'compact' => true])
                            <div class="add-package__info">
                                <strong>{{ $addable->name }}</strong>
                                @if ($addable->pricingTiers->isNotEmpty())
                                    <span class="text-muted">{{ __('core::front.packages_from') }} {{ $addable->pricingTiers->first()->displayPrice() }} {{ __($addable->priceSuffixKey()) }}</span>
                                @endif
                            </div>
                            <a href="{{ route('booking.add', ['package' => $addable->id]) }}" class="btn btn--secondary">{{ __('core::front.package_add_button') }}</a>
                        </div>
                        @endforeach
                    @endforeach
                </details>
            @endif

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
