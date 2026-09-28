<?php

use App\Models\Mission;
use App\Models\MissionLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function utilisateurMissionAvecRole(string $slug): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $role = Role::query()->where('slug', $slug)->first();
    $user->roles()->attach($role->id);

    return $user;
}

function signatureMissionTest(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

function missionEnAttenteDga(User $demandeur): Mission
{
    return Mission::query()->create([
        'demandeur_id' => $demandeur->id,
        'beneficiaire_id' => $demandeur->id,
        'objet' => 'FORMATION',
        'description' => 'Mission de test DGA',
        'priorite' => 'normale',
        'date_debut' => now()->toDateString(),
        'date_fin' => now()->addDays(7)->toDateString(),
        'budget' => 0,
        'current_step' => Mission::STEP_ATTENTE_DGA,
        'status' => 'en_cours',
    ]);
}

test('la validation DGA transmet au DG lorsqu un MD est parametre', function () {
    $demandeur = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $dga = utilisateurMissionAvecRole('dga');
    utilisateurMissionAvecRole('md');
    $mission = missionEnAttenteDga($demandeur);

    $this->actingAs($dga)
        ->post("/missions/{$mission->id}/valider-dga", [
            'signature' => signatureMissionTest(),
        ])
        ->assertRedirect();

    $mission->refresh();

    expect($mission->current_step)->toBe(Mission::STEP_ATTENTE_MD)
        ->and($mission->md_signe_at)->toBeNull();
});

test('la validation DGA saute le DG s il n y a aucun MD actif', function () {
    $demandeur = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $dga = utilisateurMissionAvecRole('dga');
    $mission = missionEnAttenteDga($demandeur);

    $this->actingAs($dga)
        ->post("/missions/{$mission->id}/valider-dga", [
            'signature' => signatureMissionTest(),
        ])
        ->assertRedirect();

    $mission->refresh();

    expect($mission->current_step)->toBe(Mission::STEP_ATTENTE_FACILITIES)
        ->and($mission->md_signe_at)->not->toBeNull();

    $this->assertDatabaseHas('mission_logs', [
        'mission_id' => $mission->id,
        'user_id' => $dga->id,
        'action' => 'approbation',
        'etape_concernee' => 'Niveau 2c — Validation DG (MD)',
    ]);
});

test('une mission bloquee a ATTENTE_MD sans DG avance vers Facilities a l ouverture', function () {
    $demandeur = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $dga = utilisateurMissionAvecRole('dga');
    $mission = missionEnAttenteDga($demandeur);
    $mission->update(['current_step' => Mission::STEP_ATTENTE_MD]);

    MissionLog::query()->create([
        'mission_id' => $mission->id,
        'user_id' => $dga->id,
        'action' => 'approbation',
        'etape_concernee' => 'Niveau 2b — Validation DGA',
        'commentaire' => 'Validation DGA accordée.',
    ]);

    $this->actingAs($dga)
        ->get("/missions/{$mission->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Missions/Show')
            ->where('mission.current_step', Mission::STEP_ATTENTE_FACILITIES)
            ->where('validationDejaEnregistree', true)
        );
});
