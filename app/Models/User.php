<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * Staff/admin user. Customers (Phase 5, Customer module) are a deliberately
 * separate model/table/guard — this project has no single "users can be
 * either staff or customer" table, since their data shapes and auth flows
 * diverge completely (2FA + roles vs. guest checkout + passport data).
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    public const string ROLE_SUPER_ADMIN = 'Super Admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function requiresTwoFactor(): bool
    {
        $enforcedRoles = config('admin.two_factor_enforced_roles', []);

        return $this->hasAnyRole($enforcedRoles);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Roles this user may hand out on the staff screens. A Super Admin can
     * assign any role; everyone else only roles whose permissions they
     * themselves already hold, and never Super Admin — so `users.manage`
     * can't be used to promote anyone (including oneself) above the
     * granting user's own level.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(): Collection
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        if ($this->isSuperAdmin()) {
            return $roles;
        }

        $own = $this->getAllPermissions()->pluck('name');

        return $roles
            ->reject(fn (Role $role): bool => $role->name === self::ROLE_SUPER_ADMIN
                || $role->permissions->pluck('name')->diff($own)->isNotEmpty())
            ->values();
    }

    /**
     * Whether this user may edit or remove $target: only when every role
     * $target holds is one this user could have assigned. Keeps a business
     * Admin away from the Super Admin's account (email, password, status).
     */
    public function canManageUser(self $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $target->getRoleNames()->diff($this->assignableRoles()->pluck('name'))->isEmpty();
    }
}
