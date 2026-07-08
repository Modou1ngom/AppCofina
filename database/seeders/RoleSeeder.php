<?php

namespace Database\Seeders;

use App\Models\Role;
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
                'nom' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Super administrateur avec tous les droits sans restriction sur l\'application',
                'actif' => true,
            ],
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
            [
                'nom' => 'Responsable RH',
                'slug' => 'responsable_rh',
                'description' => 'Responsable des Ressources Humaines — signature électronique des ordres de mission',
                'actif' => true,
            ],
            [
                'nom' => 'Profil Finance (CFO)',
                'slug' => 'finance',
                'description' => 'Profil Finance / CFO — validation des avances sur salaire (circuit CFO ou étape CFO avant MD)',
                'actif' => true,
            ],
            [
                'nom' => 'Profil MD',
                'slug' => 'md',
                'description' => 'Managing Director — validation finale des avances sur salaire lorsque le circuit RH prévoit CFO puis MD',
                'actif' => true,
            ],
            [
                'nom' => 'DGA Support',
                'slug' => 'dga',
                'description' => 'Directrice Générale Adjointe — validation et signature des demandes de mission',
                'actif' => true,
            ],
            [
                'nom' => 'Conformité',
                'slug' => 'conformite',
                'description' => 'Conformité — suivi réglementaire (ex. membres du Conseil d\'administration hors SI)',
                'actif' => true,
            ],
            [
                'nom' => 'Profil Logistique',
                'slug' => 'logistique',
                'description' => 'Service logistique — traitement des demandes de mission (véhicules, hébergements, coûts)',
                'actif' => true,
            ],
            [
                'nom' => 'Facilities / Logistique',
                'slug' => 'facilities',
                'description' => 'Alias logistique — attribution véhicules, chauffeurs et hébergements pour les missions',
                'actif' => true,
            ],
            [
                'nom' => 'Chauffeur',
                'slug' => 'chauffeur',
                'description' => 'Chauffeur — missions et ordres de déplacement',
                'actif' => true,
            ],
            [
                'nom' => 'IT',
                'slug' => 'it',
                'description' => 'Informatique — consultation de l\'historique des workflows (missions, etc.)',
                'actif' => true,
            ],
            [
                'nom' => 'Audit',
                'slug' => 'audit',
                'description' => 'Audit — consultation et contrôle des historiques de validation',
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
