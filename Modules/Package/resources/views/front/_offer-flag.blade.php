{{-- The saving a package's best offer gives. A package booked by message never reaches the step where an offer is taken. --}}
@php $flagOffer = $package->isBookableOnline() ? $package->liveOffers->sortByDesc('discount')->first() : null; @endphp
@if ($flagOffer)
    <p class="offer-flag">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 12.5V5a1.5 1.5 0 0 1 1.5-1.5h7.5l8 8a1.5 1.5 0 0 1 0 2.1l-7 7a1.5 1.5 0 0 1-2.1 0l-7.9-8.1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="8" cy="8" r="1.4" fill="currentColor"/></svg>
        <span>{{ __('core::front.offer_flag', ['saving' => $flagOffer->displayDiscount(), 'package' => $flagOffer->offeredPackage->name]) }}</span>
    </p>
@endif
