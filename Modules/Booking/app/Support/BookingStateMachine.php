<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

use Modules\Booking\Exceptions\InvalidBookingTransitionException;
use Modules\Booking\Models\Booking;

/**
 * The single place the booking status lifecycle is defined (brief §5):
 * `hold → pending_payment → confirmed → active → completed`, plus
 * `cancelled`, `no_show`, `expired` reachable from the states noted below.
 * BookingService is the only caller — controllers never set `->status`
 * directly, so an invalid transition is structurally impossible to reach
 * through the UI, only through a bug, which this throws loudly on.
 */
final class BookingStateMachine
{
    /**
     * @var array<string, string[]>
     */
    private const array TRANSITIONS = [
        Booking::STATUS_HOLD => [Booking::STATUS_PENDING_PAYMENT, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED],
        Booking::STATUS_PENDING_PAYMENT => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED],
        Booking::STATUS_CONFIRMED => [Booking::STATUS_ACTIVE, Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW],
        Booking::STATUS_ACTIVE => [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED],
        Booking::STATUS_COMPLETED => [],
        Booking::STATUS_CANCELLED => [],
        Booking::STATUS_NO_SHOW => [],
        Booking::STATUS_EXPIRED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertCanTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw InvalidBookingTransitionException::from($from, $to);
        }
    }
}
