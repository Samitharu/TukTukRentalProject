<?php

declare(strict_types=1);

namespace Modules\Booking\Exceptions;

use RuntimeException;

/**
 * The coupon priced into this checkout reached its usage limit before the
 * booking committed (another customer redeemed the last use first).
 */
final class CouponUnavailableException extends RuntimeException
{
    public static function make(string $code): self
    {
        return new self("Coupon {$code} is no longer available.");
    }
}
