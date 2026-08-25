<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The administrator is created here and nowhere else: specification 3.1 forbids
 * any public form from producing this role.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@autokrili.dz'],
            [
                'name' => 'Administrateur',
                'password' => Hash::make('password'),
                'phone' => '+213 555 00 00 00',
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles([User::ROLE_ADMIN]);
    }
}
