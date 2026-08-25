<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Administration
            'agencies.moderate', 'agencies.impersonate', 'vehicles.moderate',
            'reviews.moderate', 'plans.manage', 'subscriptions.manage',
            'content.manage', 'audit.view', 'bookings.view_all',
            // Agency
            'vehicles.manage', 'bookings.manage', 'stats.view',
            'reviews.reply', 'agency.settings', 'agency.users',
            // Client
            'bookings.create', 'reviews.create',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate(User::ROLE_ADMIN)->syncPermissions(Permission::all());

        Role::findOrCreate(User::ROLE_AGENCY)->syncPermissions([
            'vehicles.manage', 'bookings.manage', 'stats.view',
            'reviews.reply', 'agency.settings', 'agency.users',
        ]);

        Role::findOrCreate(User::ROLE_CLIENT)->syncPermissions([
            'bookings.create', 'reviews.create',
        ]);
    }
}
