<x-core::layouts.public :title="__('core::front.booking_status_title').' · '.config('app.name')">
    <section class="section booking-status-section">
        <div class="container booking-status-section__container">
            <p class="booking-status-section__eyebrow">{{ __('core::front.booking_confirmation_reference') }} · {{ $booking->reference }}</p>
            <h1>{{ __('core::front.booking_status_title') }}</h1>
            <p class="booking-status-section__intro">{{ __('core::front.booking_status_intro') }}</p>

            <div class="booking-status-panel">
                <div class="booking-status-panel__state booking-status-panel__state--{{ $booking->status }}">
                    <span aria-hidden="true"></span>
                    <div>
                        <small>{{ __('core::front.booking_status_current') }}</small>
                        <strong>{{ __('core::front.booking_status_'.$booking->status) }}</strong>
                    </div>
                </div>

                <dl class="booking-status-details">
                    <div>
                        <dt>{{ __($booking->isStay() ? 'core::front.booking_status_dates_stay' : 'core::front.booking_status_dates') }}</dt>
                        <dd>{{ $booking->isStay() ? $booking->start_at->format('d M Y').' – '.$booking->checkOutDate()->format('d M Y') : $booking->rentalPeriod() }}</dd>
                    </div>
                    @if ($booking->vehicle)
                        <div>
                            <dt>{{ __($booking->isStay() ? 'core::front.booking_status_unit' : 'core::front.booking_status_vehicle') }}</dt>
                            <dd>{{ $booking->vehicle->name }}</dd>
                        </div>
                    @endif
                    @if ($booking->package)
                        <div>
                            <dt>{{ __('core::front.booking_status_package') }}</dt>
                            <dd>{{ $booking->package->name }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <a href="{{ route('home') }}" class="btn btn--secondary booking-status-section__home">{{ __('core::front.booking_confirmation_back_home') }}</a>
        </div>
    </section>
</x-core::layouts.public>