<?php

declare(strict_types=1);

namespace Modules\Package\Policies;

use App\Models\User;
use Modules\Package\Models\ProductCategory;

/**
 * Categories are part of package management: the same permissions.
 */
final class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('packages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('packages.manage');
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can('packages.manage');
    }

    /** Only an empty category — its packages would otherwise lose their booking style. */
    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->can('packages.manage') && $category->packages()->doesntExist();
    }
}
