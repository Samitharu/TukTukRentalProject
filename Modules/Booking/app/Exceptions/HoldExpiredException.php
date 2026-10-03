<?php

declare(strict_types=1);

namespace Modules\Booking\Exceptions;

use RuntimeException;

final class HoldExpiredException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This booking hold has expired. Please start the booking again.');
    }
}
