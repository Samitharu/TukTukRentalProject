<?php

declare(strict_types=1);

namespace Modules\Fleet\Policies;

use App\Models\User;
use Modules\Fleet\Models\VehicleCategory;

final class VehicleCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fleet.view');
    }

    public function view(User $user, VehicleCategory $category): bool
    {
        return $user->can('fleet.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fleet.manage');
    }

    public function update(User $user, VehicleCategory $category): bool
    {
        return $user->can('fleet.manage');
    }

    public function delete(User $user, VehicleCategory $category): bool
    {
        return $user->can('fleet.manage') && $category->vehicles()->doesntExist();
    }
}
