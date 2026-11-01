<?php

declare(strict_types=1);

namespace Modules\Availability\Policies;

use App\Models\User;
use Modules\Availability\Models\TransferArea;

final class TransferAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('availability.view');
    }

    public function create(User $user): bool
    {
        return $user->can('availability.manage');
    }

    public function update(User $user, TransferArea $area): bool
    {
        return $user->can('availability.manage');
    }

    public function delete(User $user, TransferArea $area): bool
    {
        return $user->can('availability.manage');
    }
}
