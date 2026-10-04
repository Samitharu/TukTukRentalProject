<x-core::layouts.public :title="$vehicle->name.' · '.config('app.name')" :description="strip_tags((string) $vehicle->description)">
    <section class="section">
        <div class="container">
            <div class="grid grid--2">
                <div>
                    @if ($vehicle->images->isNotEmpty())
                        <img src="{{ asset('storage/'.$vehicle->images->first()->path) }}" alt="{{ $vehicle->name }}" style="border-radius:var(--radius-md);width:100%;aspect-ratio:4/3;object-fit:cover;">
                        @if ($vehicle->images->count() > 1)
                            <div class="grid" style="grid-template-columns:repeat(4,1fr);margin-top:0.5rem;">
                                @foreach ($vehicle->images->skip(1) as $image)
                                    <img src="{{ asset('storage/'.$image->path) }}" alt="" style="border-radius:var(--radius-sm);aspect-ratio:1;object-fit:cover;">
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
                <div>
                    <span class="badge">{{ $vehicle->category->name }}</span>
                    <h1 style="margin-top:0.5rem;">{{ $vehicle->name }}</h1>
                    <p class="text-muted">{{ $vehicle->description }}</p>

                    @if ($vehicle->isStay())
                        <p>{{ __('core::front.stays_guests', ['count' => $vehicle->seats]) }}</p>
                    @else
                        <dl style="display:grid;grid-template-columns:auto 1fr;gap:0.5rem 1rem;">
                            <dt class="text-muted">{{ __('core::front.fleet_seats', ['count' => '']) }}</dt><dd>{{ $vehicle->seats }}</dd>
                            <dt class="text-muted">{{ __('core::front.fleet_transmission') }}</dt><dd>{{ ucfirst((string) $vehicle->transmission) }}</dd>
                            <dt class="text-muted">{{ __('core::front.fleet_fuel_type') }}</dt><dd>{{ ucfirst((string) $vehicle->fuel_type) }}</dd>
                        </dl>
                    @endif

                    @if (!empty($vehicle->features))
                        <h2 style="font-size:var(--font-size-base);">{{ $vehicle->isStay() ? __('core::front.stays_amenities') : __('core::front.fleet_features') }}</h2>
                        <ul>
                            @foreach ($vehicle->features as $feature)
                                <li>{{ ucwords(str_replace('_', ' ', $feature)) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <a href="{{ route('booking.start', ['vehicle' => $vehicle->id]) }}" class="btn btn--primary btn--block">{{ $vehicle->isStay() ? __('core::front.stays_book_this') : __('core::front.fleet_book_this') }}</a>
                </div>
            </div>

            @if ($vehicle->hasLocation())
                @include('fleet::front._location', ['unit' => $vehicle])
            @endif
        </div>
    </section>
</x-core::layouts.public>
