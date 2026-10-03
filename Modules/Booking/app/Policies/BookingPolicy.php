<?php

declare(strict_types=1);

namespace Modules\Booking\Policies;

use App\Models\User;
use Modules\Booking\Models\Booking;

final class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can('bookings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.manage');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->can('bookings.manage');
    }
}
