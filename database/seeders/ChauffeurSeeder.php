<?php

namespace Database\Seeders;

use App\Models\Profil;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ChauffeurSeeder extends Seeder
{
    public function run(): void
    {
        $chauffeurUser = User::firstOrCreate(
            ['email' => 'chauffeur@example.com'],
            [
                'name' => 'Chauffeur User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $chauffeurRole = Role::where('slug', 'chauffeur')->first();

        if ($chauffeurRole && ! $chauffeurUser->roles()->where('role_id', $chauffeurRole->id)->exists()) {
            $chauffeurUser->roles()->attach($chauffeurRole->id);
        }

        $profil = Profil::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', ['chauffeur@example.com'])
            ->first();

        if ($profil === null) {
            $profil = Profil::query()->create([
                'matricule' => 'CHF001',
                'prenom' => 'Chauffeur',
                'nom' => 'Test',
                'fonction' => 'Chauffeur',
                'departement' => 'Facilities',
                'email' => 'chauffeur@example.com',
                'site' => 'SIEGE',
                'statut' => 'actif',
            ]);
        }

        if ($chauffeurRole && ! $profil->roles()->where('role_id', $chauffeurRole->id)->exists()) {
            $profil->roles()->attach($chauffeurRole->id);
        }
    }
}
