<?php

declare(strict_types=1);

namespace Modules\Booking\Exceptions;

use RuntimeException;
use Throwable;

final class NoVehicleAvailableException extends RuntimeException
{
    public static function make(?Throwable $previous = null): self
    {
        return new self('No vehicle is available for the requested dates.', 0, $previous);
    }
}
