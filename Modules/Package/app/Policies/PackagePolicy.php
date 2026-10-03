<?php

declare(strict_types=1);

namespace Modules\Package\Policies;

use App\Models\User;
use Modules\Package\Models\Package;

final class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('packages.view');
    }

    public function view(User $user, Package $package): bool
    {
        return $user->can('packages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('packages.manage');
    }

    public function update(User $user, Package $package): bool
    {
        return $user->can('packages.manage');
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->can('packages.manage');
    }
}
