/*
 * Location maps for bookable units (Leaflet + OpenStreetMap tiles — no
 * Google API key needed). Two flavours:
 *
 *   [data-location-map][data-lat][data-lng]   read-only map with one pin
 *   [data-location-picker]                    admin picker: paste a Google
 *                                             Maps link, click or drag the
 *                                             pin; fills the lat/lng inputs
 *
 * Plain JS (no Alpine): the public site runs Alpine's CSP build, which
 * can't host a third-party widget like this inside x-data.
 */
(function () {
    'use strict';

    if (typeof window.L === 'undefined') {
        return;
    }

    var TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    var ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    var SRI_LANKA = [7.8731, 80.7718];

    // Same patterns, same order, as Modules\Core\Support\GoogleMapsLink.
    var PATTERNS = [
        /!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/,
        /[?&](?:q|query|ll|destination|daddr|center)=(?:loc:)?\+?(-?\d{1,2}(?:\.\d+)?),\s*\+?(-?\d{1,3}(?:\.\d+)?)/,
        /\/(?:place|search|dir)\/\+?(-?\d{1,2}\.\d+),\s*\+?(-?\d{1,3}\.\d+)/,
        /@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/,
        /^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$/
    ];

    function decode(text) {
        try {
            return decodeURIComponent(decodeURIComponent(text));
        } catch (e) {
            return text;
        }
    }

    function coordinatesFrom(text) {
        var decoded = decode(text.trim());

        for (var i = 0; i < PATTERNS.length; i++) {
            var match = decoded.match(PATTERNS[i]);

            if (!match) {
                continue;
            }

            var lat = parseFloat(match[1]);
            var lng = parseFloat(match[2]);

            if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180 && !(lat === 0 && lng === 0)) {
                return { lat: lat, lng: lng };
            }
        }

        return null;
    }

    function baseMap(element, center, zoom) {
        var map = L.map(element, { scrollWheelZoom: false }).setView(center, zoom);

        L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);

        return map;
    }

    function initDisplayMap(element) {
        var lat = parseFloat(element.getAttribute('data-lat'));
        var lng = parseFloat(element.getAttribute('data-lng'));

        if (isNaN(lat) || isNaN(lng)) {
            return;
        }

        var map = baseMap(element, [lat, lng], 15);
        L.marker([lat, lng], { title: element.getAttribute('data-label') || '', keyboard: false }).addTo(map);
    }

    function initPicker(root) {
        var mapElement = root.querySelector('[data-location-map]');
        var latInput = root.querySelector('[data-location-lat]');
        var lngInput = root.querySelector('[data-location-lng]');
        var linkInput = root.querySelector('[data-location-link]');
        var status = root.querySelector('[data-location-status]');

        if (!mapElement || !latInput || !lngInput) {
            return;
        }

        var startLat = parseFloat(latInput.value);
        var startLng = parseFloat(lngInput.value);
        var hasStart = !isNaN(startLat) && !isNaN(startLng);
        var map = baseMap(mapElement, hasStart ? [startLat, startLng] : SRI_LANKA, hasStart ? 16 : 7);
        var marker = null;

        function say(message, warn) {
            if (!status) {
                return;
            }

            status.textContent = message || '';
            status.classList.toggle('admin-location__status--warn', !!warn);
        }

        function place(lat, lng, pan) {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);

            if (marker === null) {
                marker = L.marker([lat, lng], { draggable: true, autoPan: true }).addTo(map);
                marker.on('dragend', function () {
                    var position = marker.getLatLng();
                    place(position.lat, position.lng, false);
                });
            } else {
                marker.setLatLng([lat, lng]);
            }

            if (pan) {
                map.setView([lat, lng], Math.max(map.getZoom(), 16));
            }
        }

        function clearPin() {
            latInput.value = '';
            lngInput.value = '';

            if (marker !== null) {
                map.removeLayer(marker);
                marker = null;
            }
        }

        if (hasStart) {
            place(startLat, startLng, false);
        }

        map.on('click', function (event) {
            place(event.latlng.lat, event.latlng.lng, false);
            say('');
        });

        if (linkInput) {
            linkInput.addEventListener('input', function () {
                var value = linkInput.value.trim();

                if (value === '') {
                    say('');
                    return;
                }

                var found = coordinatesFrom(value);

                if (found) {
                    place(found.lat, found.lng, true);

                    // Bare "lat, lng" typed into the link box: it isn't a
                    // link, so move it into the coordinate fields only.
                    if (!/^https?:\/\//i.test(value)) {
                        linkInput.value = '';
                    }

                    say(root.getAttribute('data-msg-placed'));
                } else if (/(^|\/\/)(maps\.app\.)?goo\.gl\//i.test(value)) {
                    // The browser can't follow a short link (cross-origin);
                    // the server reads it on save — don't keep a stale pin.
                    clearPin();
                    say(root.getAttribute('data-msg-short'));
                } else {
                    say(root.getAttribute('data-msg-unreadable'), true);
                }
            });
        }

        function fromInputs() {
            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);

            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                place(lat, lng, true);
            }
        }

        latInput.addEventListener('change', fromInputs);
        lngInput.addEventListener('change', fromInputs);
    }

    function init() {
        document.querySelectorAll('[data-location-picker]').forEach(initPicker);
        document.querySelectorAll('[data-location-map][data-lat]').forEach(initDisplayMap);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
