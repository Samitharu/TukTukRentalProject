<?php

declare(strict_types=1);

namespace Modules\Booking\Exceptions;

use RuntimeException;

final class InvalidBookingTransitionException extends RuntimeException
{
    public static function from(string $from, string $to): self
    {
        return new self("Cannot transition a booking from \"{$from}\" to \"{$to}\".");
    }
}
