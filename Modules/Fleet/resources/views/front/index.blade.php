{{-- Shared by /tuk-tuks (Tuk Tuk line) and /vehicles (Vehicle Rent line). --}}
<x-core::layouts.public :title="$title.' · '.config('app.name')" :description="$intro">
    <section class="section">
        <div class="container">
            <h1>{{ $title }}</h1>
            <p class="text-muted">{{ $intro }}</p>

            @if ($categories->count() > 1)
                <nav aria-label="{{ $title }}" style="margin-bottom:1.5rem;">
                    <a href="{{ route($listRoute) }}" class="btn btn--secondary" style="min-height:36px;padding:0.4rem 1rem;{{ request()->missing('category') ? 'background:var(--color-primary-500);color:#fff;' : '' }}">{{ $title }}</a>
                    @foreach ($categories as $category)
                        <a href="{{ route($listRoute, ['category' => $category->id]) }}" class="btn btn--secondary" style="min-height:36px;padding:0.4rem 1rem;{{ (int) request('category') === $category->id ? 'background:var(--color-primary-500);color:#fff;' : '' }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($vehicles->isEmpty())
                <p>{{ __('core::front.fleet_no_vehicles') }}</p>
            @else
                <div class="grid grid--3">
                    @foreach ($vehicles as $vehicle)
                        <article class="card fleet-card">
                            @if ($vehicle->primaryImage())
                                <img src="{{ asset('storage/'.$vehicle->primaryImage()->path) }}" alt="{{ $vehicle->name }}" loading="lazy">
                            @endif
                            <div class="card__body">
                                <span class="badge">{{ $vehicle->category->name }}</span>
                                <h2 style="font-size:var(--font-size-lg);margin-top:0.5rem;">{{ $vehicle->name }}</h2>
                                <p class="text-muted">{{ __('core::front.fleet_seats', ['count' => $vehicle->seats]) }} &middot; {{ ucfirst($vehicle->transmission) }}</p>
                                <a href="{{ route('fleet.show', $vehicle->slugFor(app()->getLocale()) ?? $vehicle->id) }}" class="btn btn--secondary">{{ __('core::front.fleet_view_details') }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($bookLine)
                <p style="margin-top:var(--space-4);"><a href="{{ route('booking.start', ['line' => $bookLine]) }}" class="btn btn--primary">{{ __('core::front.book_now') }}</a></p>
            @endif
        </div>
    </section>
</x-core::layouts.public>
