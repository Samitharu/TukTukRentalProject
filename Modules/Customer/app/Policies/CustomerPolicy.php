<?php

declare(strict_types=1);

namespace Modules\Customer\Policies;

use App\Models\User;
use Modules\Customer\Models\Customer;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }
}
