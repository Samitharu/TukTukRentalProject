<?php

declare(strict_types=1);

namespace Modules\Booking\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Models\Booking;

/**
 * A brand-new booking was created (public checkout or admin manual
 * booking) — fired once per booking from BookingService::confirmHold(),
 * never for its idempotent replays.
 *
 * ShouldDispatchAfterCommit: confirmHold() fires this from inside its
 * transaction, so listeners only ever run once the booking has really been
 * committed — a rolled-back attempt never emails anyone about a booking
 * that doesn't exist.
 */
final class BookingPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Booking $booking)
    {
    }
}
