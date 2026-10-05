<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

use Carbon\CarbonImmutable;
use Modules\Package\Models\Package;

/**
 * The rules for an hourly rental, shared by the public flow and admin
 * manual bookings: whole-hour start times within the opening window
 * (config booking.hourly), a length within the package's min/max hours,
 * and back the same day. The tuk tuk itself is reserved for the whole day.
 */
final class HourlySchedule
{
    /**
     * Start times a customer can pick ("06:00", "07:00"…), leaving room for
     * the package's minimum length before the last return hour.
     *
     * @return string[]
     */
    public static function startTimes(Package $package): array
    {
        $last = self::lastReturnHour() - self::minHours($package);

        return $last < self::firstStartHour()
            ? []
            : array_map(fn (int $hour) => sprintf('%02d:00', $hour), range(self::firstStartHour(), $last));
    }

    /**
     * @return int[]
     */
    public static function lengths(Package $package): array
    {
        return range(self::minHours($package), max(self::maxHours($package), self::minHours($package)));
    }

    /**
     * Null when valid, otherwise a translated message.
     */
    public static function problem(Package $package, string $date, mixed $startTime, mixed $hours): ?string
    {
        if (! is_string($startTime) || ! in_array($startTime, self::startTimes($package), true)
            || ! is_numeric($hours) || ! in_array((int) $hours, self::lengths($package), true)) {
            return __('core::front.booking_hourly_invalid');
        }

        $startHour = (int) substr($startTime, 0, 2);

        if ($startHour + (int) $hours > self::lastReturnHour()) {
            return __('core::front.booking_hourly_too_late', ['time' => sprintf('%02d:00', self::lastReturnHour())]);
        }

        if (self::range($date, $startTime, (int) $hours)[0]->lte(CarbonImmutable::now())) {
            return __('core::front.booking_hourly_in_past');
        }

        return null;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $date, string $startTime, int $hours): array
    {
        $start = CarbonImmutable::parse($date.' '.$startTime);

        return [$start, $start->addHours($hours)];
    }

    private static function minHours(Package $package): int
    {
        return max((int) $package->min_hours, 1);
    }

    private static function maxHours(Package $package): int
    {
        return $package->max_hours ?? (self::lastReturnHour() - self::firstStartHour());
    }

    private static function firstStartHour(): int
    {
        return (int) config('booking.hourly.first_start_hour');
    }

    private static function lastReturnHour(): int
    {
        return (int) config('booking.hourly.last_return_hour');
    }
}
