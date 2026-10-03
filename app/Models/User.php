<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
}
