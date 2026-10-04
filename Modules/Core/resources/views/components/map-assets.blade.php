{{-- Leaflet (vendored, self-hosted — no CSP change, no Google API key) plus
     public/assets/js/maps.js, which wires up every [data-location-map] and
     [data-location-picker] on the page. Safe to include more than once. --}}
@once
    <link rel="stylesheet" href="{{ asset_v('assets/vendor/leaflet/leaflet.css') }}" nonce="{{ csp_nonce() }}">
    <script src="{{ asset_v('assets/vendor/leaflet/leaflet.js') }}" defer nonce="{{ csp_nonce() }}"></script>
    <script src="{{ asset_v('assets/js/maps.js') }}" defer nonce="{{ csp_nonce() }}"></script>
@endonce
