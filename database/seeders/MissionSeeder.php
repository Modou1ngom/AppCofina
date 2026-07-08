<?php

namespace Database\Seeders;

use App\Models\Mission;
use App\Models\MissionLog;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Recréation de votre utilisateur de test principal
        $userTest = User::where('email', 'test@example.com')->first();
        if (!$userTest) {
            $userTest = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]);
            
            // Attacher le rôle admin existant
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                $userTest->roles()->attach($adminRole->id);
            }
        }

        // 2. Création d'un collaborateur factice pour simuler l'équipe
        $collaborateur = User::where('email', 'collab@example.com')->first() ?? User::create([
            'name' => 'Amadou Diop',
            'email' => 'collab@example.com',
            'password' => Hash::make('password'),
            'manager_id' => $userTest->id, // Test User est son N+1 !
            'is_active' => true,
            'must_change_password' => false,
        ]);

        // --- ENVOI DE DEUX MISSIONS DE TEST DANS LA BASE ---
        
        // Mission A : En attente de validation
        $mA = Mission::create([
            'demandeur_id' => $userTest->id,
            'beneficiaire_id' => $collaborateur->id,
            'n2_beneficiaire_id' => null, // Supposons pas de N+2 pour le test admin
            'objet' => 'Audit annuel de l’agence COFINA Dakar Plateau',
            'perimetre' => 'Agence Plateau - Zone Centre',
            'priorite' => 'urgente',
            'date_debut' => now()->addDays(2)->format('Y-m-d'),
            'date_fin' => now()->addDays(7)->format('Y-m-d'),
            'budget' => 450000.00,
            'current_step' => 'ATTENTE_N2',
            'status' => 'en_cours'
        ]);

        MissionLog::create([
            'mission_id' => $mA->id,
            'user_id' => $userTest->id,
            'action' => 'soumission',
            'etape_concernee' => 'Initialisation',
            'commentaire' => 'Mission initialisée par le manager.'
        ]);
    }
}
