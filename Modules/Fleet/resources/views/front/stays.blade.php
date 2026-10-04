<x-core::layouts.public :title="__('core::front.stays_title').' · '.config('app.name')" :description="__('core::front.stays_intro')">
    <section class="section">
        <div class="container">
            <h1>{{ __('core::front.stays_title') }}</h1>
            <p class="text-muted">{{ __('core::front.stays_intro') }}</p>

            @if ($categories->count() > 1)
                <nav aria-label="{{ __('core::front.nav_stays') }}" style="margin-bottom:1.5rem;">
                    <a href="{{ route('stays.index') }}" class="btn btn--secondary" style="min-height:36px;padding:0.4rem 1rem;{{ request()->missing('category') ? 'background:var(--color-primary-500);color:#fff;' : '' }}">{{ __('core::front.stays_all') }}</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('stays.index', ['category' => $category->id]) }}" class="btn btn--secondary" style="min-height:36px;padding:0.4rem 1rem;{{ (int) request('category') === $category->id ? 'background:var(--color-primary-500);color:#fff;' : '' }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($units->isEmpty())
                <p>{{ __('core::front.stays_no_units') }}</p>
            @else
                <div class="grid grid--3">
                    @foreach ($units as $unit)
                        <article class="card fleet-card">
                            @if ($unit->primaryImage())
                                <img src="{{ asset('storage/'.$unit->primaryImage()->path) }}" alt="{{ $unit->name }}" loading="lazy">
                            @endif
                            <div class="card__body">
                                <span class="badge">{{ $unit->category->name }}</span>
                                <h2 style="font-size:var(--font-size-lg);margin-top:0.5rem;">{{ $unit->name }}</h2>
                                <p class="text-muted">
                                    {{ __('core::front.stays_guests', ['count' => $unit->seats]) }}
                                    @if ($unit->address)
                                        &middot; {{ $unit->address }}
                                    @endif
                                </p>
                                <a href="{{ route('stays.show', $unit->slugFor(app()->getLocale()) ?? $unit->id) }}" class="btn btn--secondary">{{ __('core::front.fleet_view_details') }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-core::layouts.public>
