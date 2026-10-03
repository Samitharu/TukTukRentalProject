<?php

declare(strict_types=1);

namespace Modules\Package\Policies;

use App\Models\User;
use Modules\Package\Models\Addon;

final class AddonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('packages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('addons.manage');
    }

    public function update(User $user, Addon $addon): bool
    {
        return $user->can('addons.manage');
    }

    public function delete(User $user, Addon $addon): bool
    {
        return $user->can('addons.manage');
    }
}
