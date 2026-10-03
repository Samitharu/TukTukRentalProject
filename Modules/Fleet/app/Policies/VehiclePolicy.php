<?php

declare(strict_types=1);

namespace Modules\Fleet\Policies;

use App\Models\User;
use Modules\Fleet\Models\Vehicle;

final class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fleet.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can('fleet.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fleet.manage');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('fleet.manage');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('fleet.manage');
    }
}
