<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'nom' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrateur avec tous les droits sur l\'application',
                'actif' => true,
            ],
            [
                'nom' => 'Metier',
                'slug' => 'metier',
                'description' => 'Profil métier qui regroupe N et l\'ensemble de N-1',
                'actif' => true,
            ],
            [
                'nom' => 'Controle',
                'slug' => 'controle',
                'description' => 'Profil de contrôle et validation',
                'actif' => true,
            ],
            [
                'nom' => 'RH',
                'slug' => 'rh',
                'description' => 'Ressources Humaines - Gestion de l\'enrôlement des employés',
                'actif' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'nom' => $roleData['nom'],
                    'slug' => $roleData['slug'],
                    'description' => $roleData['description'],
                    'actif' => $roleData['actif'],
                ]
            );
        }
    }
}

