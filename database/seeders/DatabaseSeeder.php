<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Seed des applications et rôles en premier
        $this->call([
            ApplicationSeeder::class,
            RoleSeeder::class,
        ]);

        // Créer l'utilisateur de test initial
        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && !$testUser->roles()->where('role_id', $adminRole->id)->exists()) {
            $testUser->roles()->attach($adminRole->id);
        }

        // Créer un utilisateur SuperAdmin
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        if ($superAdminRole && !$superAdminUser->roles()->where('role_id', $superAdminRole->id)->exists()) {
            $superAdminUser->roles()->attach($superAdminRole->id);
        }

        // Créer un utilisateur Admin supplémentaire
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        if ($adminRole && !$adminUser->roles()->where('role_id', $adminRole->id)->exists()) {
            $adminUser->roles()->attach($adminRole->id);
        }

        // Créer un utilisateur RH
        $rhUser = User::firstOrCreate(
            ['email' => 'rh@example.com'],
            [
                'name' => 'RH User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        $rhRole = Role::where('slug', 'rh')->first();
        if ($rhRole && !$rhUser->roles()->where('role_id', $rhRole->id)->exists()) {
            $rhUser->roles()->attach($rhRole->id);
        }

        // Responsable RH (signature des ordres de mission)
        $rrhUser = User::firstOrCreate(
            ['email' => 'rrh@example.com'],
            [
                'name' => 'Responsable RH',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        $rrhRole = Role::where('slug', 'responsable_rh')->first();
        if ($rrhRole && ! $rrhUser->roles()->where('role_id', $rrhRole->id)->exists()) {
            $rrhUser->roles()->attach($rrhRole->id);
        }

        // Créer un utilisateur Finance
        $financeUser = User::firstOrCreate(
            ['email' => 'finance@example.com'],
            [
                'name' => 'Finance User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        $financeRole = Role::where('slug', 'finance')->first();
        if ($financeRole && !$financeUser->roles()->where('role_id', $financeRole->id)->exists()) {
            $financeUser->roles()->attach($financeRole->id);
        }

        // Créer un utilisateur Facilities
        $facilitiesUser = User::firstOrCreate(
            ['email' => 'facilities@example.com'],
            [
                'name' => 'Facilities User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        foreach (['logistique', 'facilities'] as $logistiqueSlug) {
            $logistiqueRole = Role::where('slug', $logistiqueSlug)->first();
            if ($logistiqueRole && ! $facilitiesUser->roles()->where('role_id', $logistiqueRole->id)->exists()) {
                $facilitiesUser->roles()->attach($logistiqueRole->id);
            }
        }

        $this->call([
            DgaSeeder::class,
            MdSeeder::class,
            ItSeeder::class,
            AuditSeeder::class,
            RrhSeeder::class,
            ChauffeurSeeder::class,
        ]);
    }
}
