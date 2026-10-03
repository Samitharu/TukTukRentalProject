<x-core::layouts.public :title="__('core::front.booking_package_title').' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:48rem;">
            @include('booking::front.steps._indicator', ['current' => 'package'])

            <h1>{{ __('core::front.booking_package_title') }}</h1>
            <p class="text-muted">{{ __('core::front.booking_package_intro', ['days' => $days]) }}</p>

            @if ($preselectedVehicle)
                <div class="alert" style="background:var(--color-primary-50);color:var(--color-primary-600);">
                    {{ __('core::front.booking_vehicle_preselected', ['vehicle' => $preselectedVehicle->name]) }}
                </div>
            @endif

            @error('package_id')<p class="field-error">{{ $message }}</p>@enderror

            @if ($packages->isEmpty())
                <p>{{ __('core::front.booking_no_packages_available') }}</p>
                <a href="{{ route('booking.start') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
            @else
                <form method="POST" action="{{ route('booking.package.store') }}">
                    @csrf
                    <div class="grid grid--2">
                        @foreach ($packages as $package)
                            <label class="card package-card package-option">
                                @if ($package->primaryImage())
                                    <img src="{{ asset('storage/'.$package->primaryImage()->path) }}" alt="{{ $package->name }}" loading="lazy">
                                @endif
                                <div class="card__body">
                                    <input type="radio" name="package_id" value="{{ $package->id }}" class="package-option__radio"@checked((string) old('package_id', $state['package_id'] ?? '') === (string) $package->id) required>
                                    <h2 style="font-size:var(--font-size-lg);">{{ $package->name }}</h2>
                                    <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $package->description), 110) }}</p>
                                    @if ($package->pricingTiers->isNotEmpty())
                                        <p><strong>{{ __('core::front.packages_from') }} {{ $package->pricingTiers->first()->price }} {{ config('pricing.default_currency') }}</strong> {{ __('core::front.packages_per_day') }}</p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                        <a href="{{ route('booking.start') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
                        <button type="submit" class="btn btn--primary" style="flex:1;">{{ __('core::front.booking_continue') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </section>
</x-core::layouts.public>
