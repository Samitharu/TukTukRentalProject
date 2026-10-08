<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads a location out of a Google Maps link pasted by staff — the
 * "Share → Copy link" URL from the app (maps.app.goo.gl/…) or any full
 * google.com/maps URL from the browser's address bar.
 *
 * No Google API key is involved: full URLs carry the coordinates in the
 * URL itself, and short links are expanded by following their redirect
 * (only ever to Google hosts — every hop is checked, so a pasted link
 * can't be used to make the server fetch anything else).
 */
final class GoogleMapsLink
{
    private const array SHORT_LINK_HOSTS = ['maps.app.goo.gl', 'goo.gl'];

    private const int MAX_REDIRECTS = 5;

    /**
     * Coordinate patterns, most precise first: `!3d…!4d…` is the dropped
     * pin of a place, query parameters are explicit points, and `@lat,lng`
     * is only the map's centre when the link was copied.
     */
    private const array COORDINATE_PATTERNS = [
        '/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',
        '/[?&](?:q|query|ll|destination|daddr|center)=(?:loc:)?\+?(-?\d{1,2}(?:\.\d+)?),\s*\+?(-?\d{1,3}(?:\.\d+)?)/',
        '#/(?:place|search|dir)/\+?(-?\d{1,2}\.\d+),\s*\+?(-?\d{1,3}\.\d+)#',
        '/@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',
        '/^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$/',
    ];

    public static function isGoogleMapsUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'maps.app.goo.gl') {
            return true;
        }

        if ($host === 'goo.gl') {
            return str_starts_with($path, '/maps');
        }

        // maps.google.com, maps.google.lk, …
        if (preg_match('/^maps\.google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host) === 1) {
            return true;
        }

        // google.com/maps, www.google.co.uk/maps, …
        return preg_match('/^(www\.)?google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host) === 1
            && str_starts_with($path, '/maps');
    }

    public static function isShortLink(string $url): bool
    {
        $host = strtolower((string) parse_url(trim($url), PHP_URL_HOST));

        return in_array($host, self::SHORT_LINK_HOSTS, true);
    }

    /**
     * Coordinates written in the link (or in a bare "lat, lng" string).
     * Never touches the network.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function coordinatesFrom(string $text): ?array
    {
        // Twice: consent/redirect URLs nest the real link, encoded, inside
        // a `continue=` parameter.
        $decoded = rawurldecode(rawurldecode(trim($text)));

        foreach (self::COORDINATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $decoded, $match) !== 1) {
                continue;
            }

            $lat = (float) $match[1];
            $lng = (float) $match[2];

            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ! ($lat === 0.0 && $lng === 0.0)) {
                return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
            }
        }

        return null;
    }

    /**
     * coordinatesFrom(), and for a short link with nothing readable in it,
     * follows its redirects (Google hosts only) until a hop reveals them.
     * Returns null rather than throwing when the link can't be resolved —
     * staff can still drop the pin by hand.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function resolveCoordinates(string $url): ?array
    {
        $coordinates = self::coordinatesFrom($url);

        if ($coordinates !== null || ! self::isShortLink($url) || ! self::isGoogleMapsUrl($url)) {
            return $coordinates;
        }

        $current = trim($url);

        try {
            for ($hop = 0; $hop < self::MAX_REDIRECTS; $hop++) {
                $response = Http::timeout(5)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; '.config('app.name').')'])
                    ->get($current);

                $location = $response->header('Location');

                if (! $response->redirect() || $location === '') {
                    return null;
                }

                if (($coordinates = self::coordinatesFrom($location)) !== null) {
                    return $coordinates;
                }

                if (! self::isGoogleHost($location)) {
                    return null;
                }

                $current = $location;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Fills validated form data's lat/lng from its `google_maps_url` when
     * the form didn't supply a pin — or supplied the old pin alongside a
     * new link (a short link the browser couldn't read, so the map still
     * showed the previous place).
     *
     * @param  array<string, mixed>  $data
     * @param  object|null  $existing  the model being edited (google_maps_url, lat, lng)
     * @return array{0: array<string, mixed>, 1: bool}  data, and whether a pasted link without coordinates could be read
     */
    public static function fillCoordinates(array $data, ?object $existing = null): array
    {
        $url = $data['google_maps_url'] ?? null;

        if (! is_string($url) || $url === '') {
            return [$data, true];
        }

        $hasPin = ($data['lat'] ?? null) !== null;
        $stalePin = $existing !== null
            && $hasPin
            && $existing->google_maps_url !== $url
            && (float) $data['lat'] === (float) $existing->lat
            && (float) ($data['lng'] ?? 0) === (float) $existing->lng;

        if ($hasPin && ! $stalePin) {
            return [$data, true];
        }

        $coordinates = self::resolveCoordinates($url);

        return $coordinates !== null
            ? [[...$data, ...$coordinates], true]
            : [$data, false];
    }

    private static function isGoogleHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, self::SHORT_LINK_HOSTS, true)
            || preg_match('/^([a-z0-9-]+\.)*google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host) === 1;
    }
}
