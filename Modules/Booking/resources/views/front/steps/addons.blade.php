@php
    $includedAddons = $addons->filter(fn ($addon) => (bool) $addon->pivot->is_included);
    $selectableAddons = $addons->reject(fn ($addon) => (bool) $addon->pivot->is_included);
    $stateAddons = $state['addons'] ?? [];
@endphp
<x-core::layouts.public :title="__('core::front.booking_addons_title').' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:34rem;">
            @include('booking::front.steps._indicator', ['current' => 'addons'])

            <h1>{{ __('core::front.booking_addons_title') }}</h1>
            <p class="text-muted">{{ __('core::front.booking_addons_intro') }}</p>

            <form class="booking-panel" method="POST" action="{{ route('booking.addons.store') }}">
                @csrf

                @if ($includedAddons->isNotEmpty())
                    <ul style="margin-bottom:var(--space-4);">
                        @foreach ($includedAddons as $addon)
                            <li>{{ $addon->name }} <span class="badge">{{ __('core::front.packages_included') }}</span></li>
                        @endforeach
                    </ul>
                @endif

                @if ($selectableAddons->isEmpty())
                    <p class="text-muted">{{ __('core::front.booking_no_addons_available') }}</p>
                @else
                    @foreach ($selectableAddons as $addon)
                        <div class="field" style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);">
                            <div>
                                <strong>{{ $addon->name }}</strong>
                                <p class="text-muted" style="margin:0;font-size:var(--font-size-sm);">
                                    {{ $addon->price }} {{ config('pricing.default_currency') }}{{ $addon->pricing_unit === 'per_day' ? ' '.__('core::front.packages_per_day') : '' }}
                                </p>
                            </div>
                            <input
                                type="number"
                                name="addons[{{ $addon->id }}]"
                                value="{{ old('addons.'.$addon->id, $stateAddons[$addon->id] ?? 0) }}"
                                min="0"
                                max="{{ $addon->max_quantity }}"
                                aria-label="{{ __('core::front.booking_addon_quantity') }} — {{ $addon->name }}"
                                style="width:5rem;min-height:var(--touch-target-min);padding:var(--space-2);border:1px solid var(--color-border);border-radius:var(--radius-sm);"
                            >
                        </div>
                    @endforeach
                @endif

                <div class="field">
                    <label for="coupon_code">{{ __('core::front.booking_coupon_code') }}</label>
                    <input type="text" id="coupon_code" name="coupon_code" value="{{ old('coupon_code', $state['coupon_code'] ?? '') }}">
                </div>

                <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                    <a href="{{ route('booking.package') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
                    <button type="submit" class="btn btn--primary" style="flex:1;">{{ __('core::front.booking_continue') }}</button>
                </div>
            </form>
        </div>
    </section>
</x-core::layouts.public>
