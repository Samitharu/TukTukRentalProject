<x-core::layouts.public :title="$package->name.' · '.config('app.name')" :description="$package->description" :alternates="$alternates" :schema="$schema" :image="$image" type="product">
    <section class="section">
        <div class="container">
            <div class="grid grid--2">
                <div>
                    @if ($package->images->isNotEmpty())
                        <img src="{{ asset('storage/'.$package->images->first()->path) }}" alt="{{ $package->name }}" style="border-radius:var(--radius-md);width:100%;aspect-ratio:4/3;object-fit:cover;margin-bottom:var(--space-3);">
                        @if ($package->images->count() > 1)
                            <div class="grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:var(--space-4);">
                                @foreach ($package->images->skip(1) as $image)
                                    <img src="{{ asset('storage/'.$image->path) }}" alt="" style="border-radius:var(--radius-sm);aspect-ratio:1;object-fit:cover;">
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <h1>{{ $package->name }}</h1>
                    <p class="text-muted">{{ $package->description }}</p>

                    @if (!empty($package->inclusions))
                        <h2 style="font-size:var(--font-size-base);">{{ __('core::front.packages_included') }}</h2>
                        <ul>
                            @foreach ((array) $package->inclusions as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($package->addons->isNotEmpty())
                        <h2 style="font-size:var(--font-size-base);">{{ __('core::front.packages_addons_available') }}</h2>
                        <ul>
                            @foreach ($package->addons as $addon)
                                <li>
                                    {{ $addon->name }}
                                    @if ($addon->pivot->is_included)
                                        <span class="badge">{{ __('core::front.packages_included') }}</span>
                                    @else
                                        &mdash; {{ $addon->price }} {{ config('pricing.default_currency') }} ({{ $addon->pricing_unit === 'per_day' ? __($package->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day') : '' }})
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="card">
                    <div class="card__body">
                        <h2 style="font-size:var(--font-size-base);">{{ __('core::front.pricing_title') }}</h2>
                        <table style="width:100%;border-collapse:collapse;">
                            @foreach ($package->pricingTiers as $tier)
                                <tr>
                                    <td style="padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                                        {{ $tier->min_days }}{{ $tier->max_days ? '–'.$tier->max_days : '+' }} {{ __($package->isStay() ? 'core::front.packages_min_nights' : 'core::front.packages_min_days', ['count' => '']) }}
                                    </td>
                                    <td style="padding:0.4rem 0;border-bottom:1px solid var(--color-border);text-align:right;font-weight:700;">
                                        {{ $tier->price }} {{ config('pricing.default_currency') }}{{ __($package->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day') }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <a href="{{ route('booking.start', ['package' => $package->id]) }}" class="btn btn--primary btn--block" style="margin-top:1.5rem;">{{ __('core::front.packages_select') }}</a>
                    </div>
                </div>
            </div>

            @if ($stayUnits->isNotEmpty())
                <h2 style="margin-top:var(--space-5);">{{ __('core::front.packages_where_you_stay') }}</h2>
                <div class="grid grid--2">
                    @foreach ($stayUnits as $unit)
                        <article class="card">
                            <div class="card__body">
                                <h3 style="font-size:var(--font-size-lg);">
                                    <a href="{{ route('stays.show', $unit->slugFor(app()->getLocale()) ?? $unit->id) }}">{{ $unit->name }}</a>
                                </h3>
                                <p class="text-muted">{{ __('core::front.stays_guests', ['count' => $unit->seats]) }}</p>
                                @if ($unit->hasLocation())
                                    @include('fleet::front._location', ['unit' => $unit, 'heading' => 'h4'])
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-core::layouts.public>
