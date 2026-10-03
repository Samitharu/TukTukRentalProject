@php
    $currency = config('pricing.default_currency');
@endphp
<x-core::layouts.public :title="__('core::front.booking_review_title').' · '.config('app.name')">
    <x-slot:scripts>
        <script nonce="{{ csp_nonce() }}">
            {{--
                public/assets/vendor/alpine.min.js is the official @alpinejs/csp
                build: its expression evaluator is a restricted-grammar
                interpreter, not `new Function(...)`, so it has no `JSON`
                global and can't run `x-data="bookingReview(JSON.parse(...))"`
                the way regular Alpine can. The fix is the pattern Alpine's own
                CSP docs recommend: register the component with Alpine.data()
                in a real <script> (plain JS, no expression-grammar limits),
                and have it read its initial data from data-* attributes
                (already HTML-escaped by Blade's {{ }}) inside init() — never
                pass anything but a bare `name()` call through the x-data
                attribute itself.
            --}}
            document.addEventListener('alpine:init', () => {
                Alpine.data('bookingReview', () => ({
                    price: {},
                    couponCode: '',
                    error: null,
                    loading: false,
                    recalcUrl: '',
                    errorText: '',
                    init() {
                        this.price = JSON.parse(this.$el.dataset.price);
                        this.couponCode = this.$el.dataset.coupon;
                        this.recalcUrl = this.$el.dataset.recalcUrl;
                        this.errorText = this.$el.dataset.errorText;
                    },
                    money(value) {
                        return Number(value).toFixed(2);
                    },
                    async applyCoupon() {
                        this.loading = true;
                        this.error = null;

                        try {
                            const response = await fetch(this.recalcUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({ coupon_code: this.couponCode }),
                            });

                            const data = await response.json();

                            if (! response.ok) {
                                this.error = data.message || this.errorText;
                                return;
                            }

                            this.price = data;
                        } catch (e) {
                            this.error = this.errorText;
                        } finally {
                            this.loading = false;
                        }
                    },
                }));
            });
        </script>
    </x-slot:scripts>

    <section class="section booking-flow">
        <div
            class="container"
            style="max-width:40rem;"
            x-data="bookingReview()"
            data-price="{{ json_encode($price->toArray()) }}"
            data-coupon="{{ $state['coupon_code'] ?? '' }}"
            data-recalc-url="{{ route('booking.review.recalculate') }}"
            data-error-text="{{ __('core::front.booking_coupon_invalid') }}"
        >
            @include('booking::front.steps._indicator', ['current' => 'review'])

            <h1>{{ __('core::front.booking_review_title') }}</h1>
            <p class="text-muted">{{ __('core::front.booking_review_intro') }}</p>

            <div class="card" style="margin-bottom:var(--space-4);">
                <div class="card__body">
                    <h2 style="font-size:var(--font-size-base);">
                        {{ __('core::front.booking_review_dates') }}
                        <a href="{{ route('booking.start') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                    </h2>
                    <p>{{ \Illuminate\Support\Carbon::parse($state['start_date'])->format('d M Y') }} &rarr; {{ \Illuminate\Support\Carbon::parse($state['end_date'])->format('d M Y') }}</p>
                    <p class="text-muted">{{ $state['pickup_type'] === 'delivery' ? __('core::front.booking_pickup_delivery') : __('core::front.booking_pickup_office') }}</p>

                    <h2 style="font-size:var(--font-size-base);">
                        {{ __('core::front.booking_review_package') }}
                        <a href="{{ route('booking.package') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                    </h2>
                    <p>{{ $package->name }}</p>

                    @if (!empty($state['addons']))
                        <h2 style="font-size:var(--font-size-base);">
                            {{ __('core::front.booking_review_addons') }}
                            <a href="{{ route('booking.addons') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                        </h2>
                        <template x-for="line in price.addon_lines" :key="line.addon_id">
                            <p x-text="line.name + ' × ' + line.quantity"></p>
                        </template>
                    @endif

                    <h2 style="font-size:var(--font-size-base);">
                        {{ __('core::front.booking_review_driver') }}
                        <a href="{{ route('booking.details') }}" style="font-weight:400;font-size:var(--font-size-sm);">{{ __('core::front.booking_review_edit') }}</a>
                    </h2>
                    <p>{{ $state['first_name'] }} {{ $state['last_name'] }} &middot; {{ $state['email'] }} &middot; {{ $state['phone'] }}</p>
                </div>
            </div>

            <div class="field" style="display:flex;gap:var(--space-2);align-items:flex-end;">
                <div style="flex:1;">
                    <label for="coupon_code">{{ __('core::front.booking_coupon_code') }}</label>
                    <input type="text" id="coupon_code" x-model="couponCode">
                </div>
                <button type="button" class="btn btn--secondary" @click="applyCoupon()" :disabled="loading" style="margin-bottom:var(--space-3);">
                    {{ __('core::front.booking_coupon_apply') }}
                </button>
            </div>
            <p class="field-error" x-show="error" x-text="error" x-cloak></p>

            <div class="price-breakdown" style="margin-bottom:var(--space-4);">
                <dl>
                    <dt>{{ __('core::front.price_base_amount') }}</dt><dd x-text="money(price.base_amount) + ' {{ $currency }}'"></dd>

                    <template x-if="price.seasonal_adjustment != 0">
                        <dt>{{ __('core::front.price_seasonal_adjustment') }}</dt>
                    </template>
                    <template x-if="price.seasonal_adjustment != 0">
                        <dd x-text="money(price.seasonal_adjustment) + ' {{ $currency }}'"></dd>
                    </template>

                    <template x-if="price.addons_total > 0">
                        <dt>{{ __('core::front.price_addons_total') }}</dt>
                    </template>
                    <template x-if="price.addons_total > 0">
                        <dd x-text="money(price.addons_total) + ' {{ $currency }}'"></dd>
                    </template>

                    <template x-if="price.delivery_fee > 0">
                        <dt>{{ __('core::front.price_delivery_fee') }}</dt>
                    </template>
                    <template x-if="price.delivery_fee > 0">
                        <dd x-text="money(price.delivery_fee) + ' {{ $currency }}'"></dd>
                    </template>

                    <dt>{{ __('core::front.price_subtotal') }}</dt><dd x-text="money(price.rental_subtotal) + ' {{ $currency }}'"></dd>

                    <template x-if="price.coupon_discount > 0">
                        <dt>{{ __('core::front.price_coupon_discount') }} (<span x-text="price.coupon_code"></span>)</dt>
                    </template>
                    <template x-if="price.coupon_discount > 0">
                        <dd x-text="'-' + money(price.coupon_discount) + ' {{ $currency }}'"></dd>
                    </template>

                    <div class="total-row" style="display:contents;">
                        <dt>{{ __('core::front.price_total') }}</dt><dd x-text="money(price.total) + ' {{ $currency }}'"></dd>
                    </div>
                </dl>
            </div>

            <p class="text-muted">{{ __('core::front.booking_pay_on_pickup_notice') }}</p>

            <form method="POST" action="{{ route('booking.confirm') }}">
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

                @error('package_id')<p class="field-error">{{ $message }}</p>@enderror

                <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                    <a href="{{ route('booking.details') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
                    <button type="submit" class="btn btn--primary" style="flex:1;">{{ __('core::front.booking_confirm_button') }}</button>
                </div>
            </form>
        </div>
    </section>
</x-core::layouts.public>
