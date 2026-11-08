<x-core::layouts.public :title="$package->name.' · '.config('app.name')" :description="$package->description" :alternates="$alternates" :schema="$schema" :image="$image" type="product">
    <section class="section">
        <div class="container">
            <div class="grid grid--2">
                <div>
                    @include('package::front._gallery-trigger', ['package' => $package, 'class' => 'package-gallery__main'])
                    @php $photos = $package->galleryImages(); @endphp
                    @if ($photos->count() > 1)
                        <div class="package-gallery__thumbs">
                            @foreach ($photos as $photo)
                                @continue($loop->first)
                                <button
                                    type="button"
                                    data-gallery="{{ json_encode($photos->map(fn ($item) => $item->url())->all()) }}"
                                    data-gallery-title="{{ $package->name }}"
                                    data-gallery-start="{{ $loop->index }}"
                                    aria-label="{{ __('core::front.gallery_photo', ['number' => $loop->iteration]) }}"
                                ><img src="{{ $photo->thumbUrl() }}" alt="" loading="lazy"></button>
                            @endforeach
                        </div>
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
                                        &mdash; {{ $addon->displayPrice() }}@if ($addon->pricing_unit === 'per_day') {{ __($package->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day') }}@endif
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
                                        {{ $tier->min_days }}{{ $tier->max_days ? '–'.$tier->max_days : '+' }} {{ __('core::front.packages_unit_'.$package->tierUnit()) }}
                                    </td>
                                    <td style="padding:0.4rem 0;border-bottom:1px solid var(--color-border);text-align:right;font-weight:700;">
                                        @include('package::front._tier-price', ['package' => $package, 'tier' => $tier])
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        @include('package::front._offer-flag', ['package' => $package])
                        @include('package::front._package-terms', ['package' => $package])

                        @if ($whatsappUrl)
                            <a href="{{ $whatsappUrl }}" class="btn btn--primary btn--block" style="margin-top:1.5rem;" target="_blank" rel="noopener">{{ __('core::front.packages_book_whatsapp') }}</a>
                            <p class="text-muted" style="font-size:var(--font-size-sm);margin-top:var(--space-2);">{{ __('core::front.packages_activity_note') }}</p>
                        @else
                            <a href="{{ route('booking.start', ['package' => $package->id]) }}" class="btn btn--primary btn--block" style="margin-top:1.5rem;">{{ __('core::front.packages_select') }}</a>
                        @endif
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
