<?php

use App\Models\Profil;
use App\Models\ProfilMouvement;
use App\Models\Role;
use App\Models\User;
use App\Services\ProfilMouvementService;
use Database\Seeders\RoleSeeder;

function utilisateurRhPourMouvements(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->withoutTwoFactor()->create([
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $role = Role::query()->where('slug', 'super_admin')->first();
    $user->roles()->attach($role->id);

    return $user;
}

function profilStaff(array $overrides = []): Profil
{
    return Profil::query()->create(array_merge([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Awa',
        'nom' => 'Fall',
        'email' => 'awa.fall.'.uniqid().'@cofina.test',
        'fonction' => 'Chargée de clientèle',
        'departement' => 'Réseau',
        'site' => 'Dakar Plateau',
        'statut' => 'actif',
        'date_entree' => now()->subYear()->toDateString(),
    ], $overrides));
}

test('une arrivee est historisee a la creation d un profil', function () {
    $user = utilisateurRhPourMouvements();

    $this->actingAs($user)
        ->post('/profils', [
            'nom' => 'Diop',
            'prenom' => 'Mamadou',
            'fonction' => 'Analyste',
            'departement' => 'RH',
            'email' => 'mamadou.diop.mouvement@cofina.test',
            'date_entree' => '2026-09-01',
            'statut' => 'actif',
        ])
        ->assertRedirect(route('profils.index'));

    $profil = Profil::query()->where('email', 'mamadou.diop.mouvement@cofina.test')->first();

    expect($profil)->not->toBeNull()
        ->and($profil->date_entree?->toDateString())->toBe('2026-09-01');

    $this->assertDatabaseHas('profil_mouvements', [
        'profil_id' => $profil->id,
        'type' => ProfilMouvement::TYPE_ARRIVEE,
        'fonction_apres' => 'Analyste',
        'departement_apres' => 'RH',
    ]);
});

test('sans date d entree le profil utilise la date de creation', function () {
    $user = utilisateurRhPourMouvements();

    $this->actingAs($user)
        ->post('/profils', [
            'nom' => 'Sow',
            'prenom' => 'Khady',
            'email' => 'khady.sow.entree@cofina.test',
            'statut' => 'actif',
        ])
        ->assertRedirect(route('profils.index'));

    $profil = Profil::query()->where('email', 'khady.sow.entree@cofina.test')->first();

    expect($profil)->not->toBeNull()
        ->and($profil->date_entree?->toDateString())->toBe($profil->created_at?->toDateString());
});

test('un depart desactive le staff et son compte', function () {
    $user = utilisateurRhPourMouvements();
    $profil = profilStaff();
    app(\App\Services\ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    $this->actingAs($user)
        ->post(route('profils.mouvements.depart.store'), [
            'profil_id' => $profil->id,
            'date_effet' => '2026-09-07',
            'motif' => 'Démission',
        ])
        ->assertRedirect(route('profils.mouvements.index'));

    $profil->refresh();
    $compte = User::query()->where('email', $profil->email)->first();

    expect($profil->statut)->toBe('inactif')
        ->and($profil->date_sortie?->toDateString())->toBe('2026-09-07')
        ->and($profil->motif_depart)->toBe('Démission')
        ->and($compte?->is_active)->toBeFalse();

    $this->assertDatabaseHas('profil_mouvements', [
        'profil_id' => $profil->id,
        'type' => ProfilMouvement::TYPE_DEPART,
        'motif' => 'Démission',
    ]);
});

test('un second depart est refuse', function () {
    $user = utilisateurRhPourMouvements();
    $profil = profilStaff();

    app(ProfilMouvementService::class)->enregistrerDepart(
        $profil,
        now(),
        'Fin de contrat',
        $user,
    );

    $this->actingAs($user)
        ->post(route('profils.mouvements.depart.store'), [
            'profil_id' => $profil->id,
            'date_effet' => now()->toDateString(),
            'motif' => 'Démission',
        ])
        ->assertSessionHasErrors('profil_id');
});

test('un changement de poste met a jour la fiche et historise avant / apres', function () {
    $user = utilisateurRhPourMouvements();
    $nPlus1 = profilStaff([
        'prenom' => 'Ibrahima',
        'nom' => 'Ndiaye',
        'email' => 'ibrahima.ndiaye.'.uniqid().'@cofina.test',
        'fonction' => 'Responsable agence',
    ]);
    $profil = profilStaff();

    $this->actingAs($user)
        ->post(route('profils.mouvements.changement-poste.store'), [
            'profil_id' => $profil->id,
            'date_effet' => '2026-09-07',
            'fonction' => 'Chef d\'agence',
            'departement' => 'Réseau',
            'site' => 'Thiès',
            'n_plus_1_id' => $nPlus1->id,
            'motif' => 'Promotion',
        ])
        ->assertRedirect(route('profils.mouvements.index'));

    $profil->refresh();

    expect($profil->fonction)->toBe('Chef d\'agence')
        ->and($profil->site)->toBe('Thiès')
        ->and($profil->n_plus_1_id)->toBe($nPlus1->id);

    $mouvement = ProfilMouvement::query()
        ->where('profil_id', $profil->id)
        ->where('type', ProfilMouvement::TYPE_CHANGEMENT_POSTE)
        ->first();

    expect($mouvement)->not->toBeNull()
        ->and($mouvement->fonction_avant)->toBe('Chargée de clientèle')
        ->and($mouvement->fonction_apres)->toBe('Chef d\'agence')
        ->and($mouvement->site_avant)->toBe('Dakar Plateau')
        ->and($mouvement->site_apres)->toBe('Thiès')
        ->and($mouvement->motif)->toBe('Promotion');
});

test('un changement de poste sans modification est refuse', function () {
    $user = utilisateurRhPourMouvements();
    $profil = profilStaff();

    $this->actingAs($user)
        ->from(route('profils.mouvements.changement-poste'))
        ->post(route('profils.mouvements.changement-poste.store'), [
            'profil_id' => $profil->id,
            'date_effet' => now()->toDateString(),
            'fonction' => $profil->fonction,
            'departement' => $profil->departement,
            'site' => $profil->site,
            'n_plus_1_id' => $profil->n_plus_1_id,
        ])
        ->assertSessionHasErrors('fonction');
});

test('lexport excel des departs ou des arrivees respecte la periode', function () {
    $user = utilisateurRhPourMouvements();
    $service = app(ProfilMouvementService::class);

    $parti = profilStaff([
        'prenom' => 'Awa',
        'nom' => 'Partie',
        'email' => 'awa.partie.'.uniqid().'@cofina.test',
    ]);
    $arrive = profilStaff([
        'prenom' => 'Moussa',
        'nom' => 'Arrive',
        'email' => 'moussa.arrive.'.uniqid().'@cofina.test',
        'date_entree' => '2026-08-17',
    ]);

    $service->enregistrerDepart($parti, \Illuminate\Support\Carbon::parse('2026-09-07'), 'Démission', $user);
    $service->enregistrerArrivee($arrive, \Illuminate\Support\Carbon::parse('2026-08-17'), 'Enrôlement staff', $user);

    $this->actingAs($user)
        ->get(route('profils.mouvements.export', ['type' => 'depart']))
        ->assertSessionHasErrors(['date_debut', 'date_fin']);

    $depart = $this->actingAs($user)->get(route('profils.mouvements.export', [
        'type' => 'depart',
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-30',
    ]));

    $depart->assertOk();
    $depart->assertDownload('departs_2026-09-01_2026-09-30.xlsx');

    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($depart->baseResponse->getFile()->getPathname())
        ->getActiveSheet();
    $values = $sheet->toArray();

    expect($values[0][0])->toBe('Date')
        ->and($values[0][8])->toBe('Motif')
        ->and(collect($values)->pluck(3)->all())->toContain('Partie')
        ->and(collect($values)->pluck(3)->all())->not->toContain('Arrive');

    $arrivee = $this->actingAs($user)->get(route('profils.mouvements.export', [
        'type' => 'arrivee',
        'date_debut' => '2026-08-01',
        'date_fin' => '2026-08-31',
    ]));

    $arrivee->assertOk();
    $noms = collect(
        \PhpOffice\PhpSpreadsheet\IOFactory::load($arrivee->baseResponse->getFile()->getPathname())
            ->getActiveSheet()
            ->toArray()
    )->pluck(3);

    expect($noms->all())->toContain('Arrive')
        ->and($noms->all())->not->toContain('Partie');
});

test('les pages mouvements rh s affichent', function () {
    $user = utilisateurRhPourMouvements();

    $this->actingAs($user)->get(route('profils.mouvements.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('profils/Mouvements/Index'));

    $this->actingAs($user)->get(route('profils.mouvements.depart'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('profils/Mouvements/Depart'));

    $this->actingAs($user)->get(route('profils.mouvements.changement-poste'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('profils/Mouvements/ChangementPoste'));
});
