{{-- A unit's location: OpenStreetMap preview plus "Open in Google Maps" /
     "Get directions" links. Expects $unit (a Vehicle, or a pickup
     BusinessLocation — both use HasMapLocation) with a location;
     $heading optionally sets the heading level for where it is nested. --}}
@php $heading = $heading ?? 'h2'; @endphp
<div class="unit-location">
    <{{ $heading }} style="font-size:var(--font-size-base);">{{ __('core::front.location_title') }}</{{ $heading }}>
    @if ($unit->address)
        <p class="text-muted">{{ $unit->address }}</p>
    @endif

    @if ($unit->hasCoordinates())
        <div class="unit-location__map" data-location-map data-lat="{{ $unit->lat }}" data-lng="{{ $unit->lng }}" data-label="{{ $unit->name }}" role="img" aria-label="{{ __('core::front.location_title') }}: {{ $unit->name }}"></div>
        <x-core::map-assets />
    @endif

    <div class="unit-location__actions">
        @if ($unit->mapUrl())
            <a href="{{ $unit->mapUrl() }}" class="btn btn--secondary" target="_blank" rel="noopener noreferrer">{{ __('core::front.location_open_map') }}</a>
        @endif
        @if ($unit->directionsUrl())
            <a href="{{ $unit->directionsUrl() }}" class="btn btn--secondary" target="_blank" rel="noopener noreferrer">{{ __('core::front.location_directions') }}</a>
        @endif
    </div>
</div>
