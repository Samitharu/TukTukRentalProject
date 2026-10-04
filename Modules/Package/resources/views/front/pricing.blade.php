@php
    $currency = config('pricing.default_currency');
@endphp
<x-core::layouts.public :title="__('core::front.pricing_title').' · '.config('app.name')" :description="__('core::front.pricing_intro', ['currency' => $currency])">
    <section class="section">
        <div class="container" style="max-width:48rem;">
            <h1>{{ __('core::front.pricing_title') }}</h1>
            <p class="text-muted">{{ __('core::front.pricing_intro', ['currency' => $currency]) }}</p>

            @foreach ($packages as $package)
                <div class="card" style="margin-bottom:var(--space-4);">
                    <div class="card__body">
                        <h2 style="font-size:var(--font-size-lg);">{{ $package->name }}</h2>
                        <p class="text-muted" style="font-size:var(--font-size-sm);">{{ __($package->isStay() ? 'core::front.packages_min_nights' : 'core::front.packages_min_days', ['count' => $package->min_days]) }}</p>

                        @if ($package->pricingTiers->isEmpty())
                            <p class="text-muted">{{ __('core::front.fleet_no_vehicles') }}</p>
                        @else
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:0.4rem 0;border-bottom:2px solid var(--color-border);">{{ __('core::front.booking_step_dates') }}</th>
                                        <th style="text-align:right;padding:0.4rem 0;border-bottom:2px solid var(--color-border);">{{ __('core::front.price_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($package->pricingTiers as $tier)
                                        <tr>
                                            <td style="padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                                                {{ $tier->min_days }}{{ $tier->max_days ? '–'.$tier->max_days : '+' }} {{ __($package->isStay() ? 'core::front.packages_min_nights' : 'core::front.packages_min_days', ['count' => '']) }}
                                            </td>
                                            <td style="padding:0.4rem 0;border-bottom:1px solid var(--color-border);text-align:right;font-weight:700;">
                                                {{ $tier->price }} {{ $currency }}{{ __($package->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <a href="{{ route('packages.show', $package->slugFor(app()->getLocale()) ?? $package->id) }}" class="btn btn--secondary" style="margin-top:var(--space-3);">{{ __('core::front.packages_view_details') }}</a>
                    </div>
                </div>
            @endforeach

            <p class="text-muted">{{ __('core::front.booking_pay_on_pickup_notice') }}</p>
        </div>
    </section>
</x-core::layouts.public>
