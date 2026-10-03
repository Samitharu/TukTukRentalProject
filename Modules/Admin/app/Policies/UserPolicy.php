<?php

declare(strict_types=1);

namespace Modules\Admin\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.manage');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.manage') && $user->isNot($target);
    }
}
