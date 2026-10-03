<?php

declare(strict_types=1);

namespace Modules\Availability\Policies;

use App\Models\User;
use Modules\Availability\Models\BusinessLocation;

final class BusinessLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('availability.view');
    }

    public function view(User $user, BusinessLocation $location): bool
    {
        return $user->can('availability.view');
    }

    public function create(User $user): bool
    {
        return $user->can('availability.manage');
    }

    public function update(User $user, BusinessLocation $location): bool
    {
        return $user->can('availability.manage');
    }

    public function delete(User $user, BusinessLocation $location): bool
    {
        return $user->can('availability.manage');
    }
}
