{{-- "from $12.00 for 2 hours": the cheapest booking of $package, or nothing when it has no price. A group-priced package also names the group, "(3–5 people)". --}}
@php $fromTier = $package->fromTier(); @endphp
@if ($fromTier)
    {{ __('core::front.packages_from') }}
    <strong>{{ $package->tierPrice($fromTier) }}</strong>
    {{ $package->tierPriceSuffix($fromTier) }}
    @if ($package->isGroupPriced())
        ({{ $package->groupSizeLabel($fromTier) }})
    @endif
@endif
