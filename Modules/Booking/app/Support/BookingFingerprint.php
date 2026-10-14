<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

/**
 * Normalizes what a customer typed at checkout into values that compare
 * equal however it was formatted, so SuspiciousBookingDetector can match
 * "+94 77 123 4567" with "0771234567".
 */
final class BookingFingerprint
{
    /**
     * The last 9 digits — drops the country code / trunk "0" difference
     * between international and local formats of the same number.
     */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 7 ? substr($digits, -9) : null;
    }

    /**
     * A keyed hash, never the passport number itself: bookings must not
     * hold a second, unencrypted copy of it.
     */
    public static function passportKey(?string $passport): ?string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper((string) $passport)) ?? '';

        return $normalized !== '' ? hash_hmac('sha256', $normalized, (string) config('app.key')) : null;
    }
}
