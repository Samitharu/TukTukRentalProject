<?php

declare(strict_types=1);

namespace Modules\Core\Support;

/**
 * A place staff pin on the map: an optional Google Maps link pasted from
 * the app plus lat/lng (read from that link, or dropped on the admin map).
 * Used by tuk tuks/stays (Fleet) and pickup offices (Availability) so the
 * customer-facing location card works for either.
 *
 * Expects `google_maps_url`, `lat` and `lng` attributes.
 */
trait HasMapLocation
{
    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function hasLocation(): bool
    {
        return $this->hasCoordinates() || filled($this->google_maps_url);
    }

    /**
     * Opens the place in Google Maps: the admin's own link when there is
     * one (it may carry the business listing, reviews, photos), otherwise
     * a search for the pinned coordinates.
     */
    public function mapUrl(): ?string
    {
        if (filled($this->google_maps_url)) {
            return $this->google_maps_url;
        }

        return $this->hasCoordinates()
            ? 'https://www.google.com/maps/search/?api=1&query='.$this->lat.','.$this->lng
            : null;
    }

    /** Turn-by-turn directions from wherever the customer is now. */
    public function directionsUrl(): ?string
    {
        return $this->hasCoordinates()
            ? 'https://www.google.com/maps/dir/?api=1&destination='.$this->lat.','.$this->lng
            : null;
    }
}
