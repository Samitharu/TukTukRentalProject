@php
    $bookablePackages = $packages->filter(fn ($package) => ($freeCounts[$package->id] ?? 0) > 0);
@endphp
<x-core::layouts.public :title="__('core::front.booking_package_title').' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:48rem;">
            @include('booking::front.steps._indicator', ['current' => 'package'])

            <h1>{{ __('core::front.booking_package_title') }}</h1>
            <p class="text-muted">{{ $isStay ? __('core::front.booking_package_intro_stay', ['nights' => $days]) : __('core::front.booking_package_intro', ['days' => $days]) }}</p>

            @error('package_id')
                <div class="alert alert--error alert--icon" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16.3" r="1.1" fill="currentColor"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            @if ($preselectedVehicle && $preselectedVehicleBooked)
                <div class="alert alert--warning alert--icon" role="status">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5 21.5 20h-19L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 10v4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17.3" r="1.1" fill="currentColor"/></svg>
                    <span>{{ __($isStay ? 'core::front.booking_unit_preselected_booked' : 'core::front.booking_vehicle_preselected_booked', ['vehicle' => $preselectedVehicle->name]) }}</span>
                </div>
            @elseif ($preselectedVehicle)
                <div class="alert" style="background:var(--color-primary-50);color:var(--color-primary-600);">
                    {{ __($isStay ? 'core::front.booking_unit_preselected' : 'core::front.booking_vehicle_preselected', ['vehicle' => $preselectedVehicle->name]) }}
                </div>
            @endif

            @if ($packages->isEmpty())
                <p>{{ __('core::front.booking_no_packages_available') }}</p>
                <a href="{{ route('booking.start') }}" class="btn btn--secondary">{{ __('core::front.booking_back') }}</a>
            @else
                @if ($bookablePackages->isEmpty())
                    <div class="alert alert--warning alert--icon" role="status">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 9.5h17M8 3v4M16 3v4M9.5 13.5l5 4M14.5 13.5l-5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        <span>{{ __($isStay ? 'core::front.booking_all_packages_fully_booked_stay' : 'core::front.booking_all_packages_fully_booked') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('booking.package.store') }}">
                    @csrf
                    <div class="grid grid--2">
                        @foreach ($packages as $package)
                            @php $free = $freeCounts[$package->id] ?? 0; @endphp
                            <label class="card package-card package-option {{ $free === 0 ? 'package-option--unavailable' : '' }}">
                                @if ($package->primaryImage())
                                    <img src="{{ asset('storage/'.$package->primaryImage()->path) }}" alt="{{ $package->name }}" loading="lazy">
                                @endif
                                <div class="card__body">
                                    <input type="radio" name="package_id" value="{{ $package->id }}" class="package-option__radio"@checked($free > 0 && (string) old('package_id', $state['package_id'] ?? '') === (string) $package->id) @disabled($free === 0) required>
                                    @if ($free === 0)
                                        <span class="availability-badge availability-badge--none">{{ __('core::front.booking_package_fully_booked') }}</span>
                                    @elseif ($free <= 2)
                                        <span class="availability-badge availability-badge--low">{{ trans_choice($isStay ? 'core::front.booking_package_few_left_stay' : 'core::front.booking_package_few_left', $free, ['count' => $free]) }}</span>
                                    @else
                                        <span class="availability-badge availability-badge--ok">{{ __('core::front.booking_package_available') }}</span>
                                    @endif
                                    <h2 style="font-size:var(--font-size-lg);">{{ $package->name }}</h2>
                                    <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $package->description), 110) }}</p>
                                    @if ($package->pricingTiers->isNotEmpty())
                                        <p><strong>{{ __('core::front.packages_from') }} {{ $package->pricingTiers->first()->price }} {{ config('pricing.default_currency') }}</strong> {{ __($package->isStay() ? 'core::front.packages_per_night' : 'core::front.packages_per_day') }}</p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                        <a href="{{ route('booking.start') }}" class="btn btn--secondary">{{ $bookablePackages->isEmpty() ? __('core::front.booking_change_dates') : __('core::front.booking_back') }}</a>
                        @if ($bookablePackages->isNotEmpty())
                            <button type="submit" class="btn btn--primary" style="flex:1;">{{ __('core::front.booking_continue') }}</button>
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </section>
</x-core::layouts.public>
