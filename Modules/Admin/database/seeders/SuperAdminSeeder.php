<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the real Super Admin account, including in production. The address
 * and password come from .env (SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD) so
 * they never land in git.
 *
 * Safe to run again: an existing account keeps its name and password, so a
 * password changed in the control panel is not reset. It only gets its Super
 * Admin role and active status back.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower(trim((string) config('admin.super_admin.email')));
        $password = (string) config('admin.super_admin.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('Super Admin skipped: set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in .env.');

            return;
        }

        $role = Role::findOrCreate(User::ROLE_SUPER_ADMIN);
        $role->syncPermissions(Permission::all());

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        if ($isNew) {
            $user->name = (string) config('admin.super_admin.name');
            $user->password = $password;
            $user->email_verified_at = now();
        }

        $user->is_active = true;
        $user->deleted_at = null;
        $user->save();
        $user->assignRole($role);

        $this->command?->info($isNew
            ? "Super Admin created: {$email}"
            : "Super Admin already existed, password left unchanged: {$email}");
    }
}
