<?php

declare(strict_types=1);

namespace Modules\Booking\Exceptions;

use RuntimeException;

final class NoVehicleAvailableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('No vehicle is available for the requested dates.');
    }
}
