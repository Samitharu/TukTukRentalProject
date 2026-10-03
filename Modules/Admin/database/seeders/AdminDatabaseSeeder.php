<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the four roles named in the brief (§5) and the permissions that
 * exist so far (Phase 2: locales, users). Every later module adds its own
 * permissions the same way — `Permission::findOrCreate('fleet.manage')` —
 * and decides which of these roles get them; this seeder is re-runnable
 * (findOrCreate / firstOrCreate throughout) so it's safe to call again
 * after a new module adds permissions.
 */
class AdminDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'locales.view',
            'locales.manage',
            'users.view',
            'users.manage',
            'fleet.view',
            'fleet.manage',
            'packages.view',
            'packages.manage',
            'addons.manage',
            'pricing.manage',
            'availability.view',
            'availability.manage',
            'bookings.view',
            'bookings.manage',
            'customers.view',
            'cms.view',
            'cms.manage',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate('Super Admin');
        $superAdmin->syncPermissions(Permission::all());

        $manager = Role::findOrCreate('Manager');
        $manager->syncPermissions([
            'locales.view', 'users.view',
            'fleet.view', 'fleet.manage',
            'packages.view', 'packages.manage', 'addons.manage',
            'pricing.manage',
            'availability.view', 'availability.manage',
            'bookings.view', 'bookings.manage', 'customers.view',
            'cms.view', 'cms.manage',
            'settings.manage',
        ]);

        $bookingAgent = Role::findOrCreate('Booking Agent');
        $bookingAgent->syncPermissions([
            'fleet.view', 'packages.view', 'availability.view',
            'bookings.view', 'bookings.manage', 'customers.view',
        ]);

        $contentEditor = Role::findOrCreate('Content Editor');
        $contentEditor->syncPermissions(['locales.view', 'cms.view', 'cms.manage']);

        if (! app()->isProduction()) {
            $superAdminUser = User::query()->updateOrCreate(
                ['email' => 'owner@mirandatuktuk.example'],
                [
                    'name' => 'Miranda Owner',
                    'password' => Hash::make('ChangeMe!12345'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
            $superAdminUser->syncRoles([$superAdmin]);
        }
    }
}
