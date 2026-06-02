<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminUserSeeder extends Seeder
{
    /**
     * Crée ou met à jour le compte super administrateur.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $superAdminRole = Role::query()->where('slug', 'super_admin')->first();

        if ($superAdminRole === null) {
            $this->command?->error('Le rôle super_admin est introuvable.');

            return;
        }

        $email = strtolower(trim((string) config('cofina.superadmin.email')));
        $name = (string) config('cofina.superadmin.name');
        $password = (string) config('cofina.superadmin.password');

        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'is_active' => true,
            'must_change_password' => config('cofina.superadmin.must_change_password', true),
        ]);

        if (! $user->exists || config('cofina.superadmin.reset_password', false)) {
            $user->password = Hash::make($password);
        }

        $user->save();

        $user->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $this->command?->info("Compte super admin : {$user->email}");
    }
}
