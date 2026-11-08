<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

use Carbon\CarbonImmutable;
use Modules\Availability\Models\TransferArea;

/**
 * The timing rules for an airport pickup, shared by the public flow and
 * admin manual bookings. Times are the local (Sri Lanka) wall-clock time
 * the customer typed, stored as-is like hourly rentals; "too soon" is
 * judged against now in config booking.business_timezone.
 */
final class TransferSchedule
{
    /** Flight numbers: airline code (2–3 letters/digits) + 1–4 digits, optional letter. */
    public const string FLIGHT_NUMBER_PATTERN = '/^[A-Z0-9]{2,3}\s?\d{1,4}[A-Z]?$/';

    /** A trip booked without a destination area: the same length an area has by default. */
    public const int MINUTES_WITHOUT_AREA = 120;

    /**
     * When the vehicle leaves and when it is free again. The destination
     * area is optional.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $date, string $time, ?TransferArea $area): array
    {
        $start = CarbonImmutable::parse($date.' '.$time);

        return [$start, $start->addMinutes(max($area?->duration_minutes ?? self::MINUTES_WITHOUT_AREA, 1))];
    }

    /** Null when the pickup time can be booked, otherwise a translated message. */
    public static function problem(string $date, mixed $time): ?string
    {
        if (! is_string($time) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return __('core::front.transfer_time_invalid');
        }

        $timezone = (string) config('booking.business_timezone');
        $pickup = CarbonImmutable::parse($date.' '.$time, $timezone);
        $noticeHours = (int) config('availability.minimum_notice_hours');

        if ($pickup->lt(CarbonImmutable::now($timezone)->addHours($noticeHours))) {
            return __('core::front.transfer_too_soon', ['hours' => $noticeHours]);
        }

        return null;
    }

    public static function normalizeFlightNumber(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
    }
}
