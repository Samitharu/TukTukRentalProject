<?php

declare(strict_types=1);

namespace Modules\Availability\Exceptions;

use RuntimeException;

/**
 * Thrown when a reservation attempt loses the race against the
 * `UNIQUE(vehicle_id, slot_date)` constraint on `vehicle_reservation_slots`
 * — the actual, final double-booking guarantee (docs/01-architecture.md §6).
 * Every caller (BookingService, admin date/vehicle changes) must catch this
 * and turn it into a user-facing "those dates are no longer available."
 */
final class SlotConflictException extends RuntimeException
{
    public static function forVehicle(int $vehicleId): self
    {
        return new self("Vehicle #{$vehicleId} is no longer available for the requested dates.");
    }
}
