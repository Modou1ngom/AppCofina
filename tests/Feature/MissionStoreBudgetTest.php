<?php

use App\Models\Mission;
use App\Models\Profil;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

test('une mission peut etre creee sans budget renseigne', function () {
    test()->seed(RoleSeeder::class);

    $user = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $user->roles()->attach(Role::query()->where('slug', 'md')->first()->id);

    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Malick',
        'nom' => 'Ngom',
        'email' => 'malick.ngom.'.uniqid().'@cofina.test',
        'fonction' => 'Chargé de clientèle',
        'departement' => 'Réseau',
        'site' => 'Dakar',
        'statut' => 'actif',
        'date_entree' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->post('/missions', [
            'participant_profil_ids' => [$profil->id],
            'objet' => 'FORMATION',
            'sites_mission' => ['Diourbel'],
            'descriptions_sites' => ['Diourbel' => 'Formation agence'],
            'description' => 'Mission de formation',
            'priorite' => 'normale',
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(),
            'action' => 'soumettre',
        ])
        ->assertRedirect(route('missions.index'));

    $mission = Mission::query()->where('objet', 'FORMATION')->first();

    expect($mission)->not->toBeNull()
        ->and((float) $mission->budget)->toBe(0.0);

    $this->assertDatabaseHas('mission_user', [
        'mission_id' => $mission->id,
        'profil_id' => $profil->id,
        'role_dans_mission' => 'missionnaire',
    ]);
});
