<?php

declare(strict_types=1);

namespace Modules\Localization\Policies;

use App\Models\User;
use Modules\Localization\Models\Locale;

final class LocalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('locales.view');
    }

    public function view(User $user, Locale $locale): bool
    {
        return $user->can('locales.view');
    }

    public function create(User $user): bool
    {
        return $user->can('locales.manage');
    }

    public function update(User $user, Locale $locale): bool
    {
        return $user->can('locales.manage');
    }

    public function delete(User $user, Locale $locale): bool
    {
        return $user->can('locales.manage') && ! $locale->is_default;
    }
}
