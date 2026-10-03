<?php

declare(strict_types=1);

namespace Modules\Availability\Policies;

use App\Models\User;
use Modules\Availability\Models\AvailabilityBlackout;

final class AvailabilityBlackoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('availability.view');
    }

    public function create(User $user): bool
    {
        return $user->can('availability.manage');
    }

    public function delete(User $user, AvailabilityBlackout $blackout): bool
    {
        return $user->can('availability.manage');
    }
}
