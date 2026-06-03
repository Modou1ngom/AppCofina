<?php

use App\Models\Profil;
use App\Services\ProfilUserProvisioningService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

test('un compte utilisateur est créé lors de la création d un profil avec e-mail', function () {
    config([
        'cofina.provision_user_on_profil_create' => true,
        'cofina.default_user_password' => 'Cofina@2025',
    ]);

    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Jean',
        'nom' => 'Dupont',
        'email' => 'nouveau.collab@cofina.test',
        'statut' => 'actif',
    ]);

    $user = app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('nouveau.collab@cofina.test')
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('Cofina@2025', $user->password))->toBeTrue();
});

test('aucun compte n est créé sans e-mail sur le profil', function () {
    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Marie',
        'nom' => 'Diop',
        'email' => null,
        'statut' => 'actif',
    ]);

    $user = app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    expect($user)->toBeNull();
});

test('le role admin est assigne pour un profil du departement informatique', function () {
    $this->seed(RoleSeeder::class);

    config([
        'cofina.provision_user_on_profil_create' => true,
        'cofina.default_user_password' => 'Cofina@2025',
    ]);

    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Ali',
        'nom' => 'Ndiaye',
        'email' => 'ali.ndiaye.it@cofina.test',
        'departement' => 'IT',
        'statut' => 'actif',
    ]);

    $user = app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    expect($user)->not->toBeNull()
        ->and($user->hasRole('admin'))->toBeTrue()
        ->and($profil->fresh()->roles()->where('slug', 'admin')->exists())->toBeTrue();
});

test('le role rh est assigne pour un profil du departement rh', function () {
    $this->seed(RoleSeeder::class);

    config(['cofina.provision_user_on_profil_create' => true]);

    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Fatou',
        'nom' => 'Sarr',
        'email' => 'fatou.sarr.rh@cofina.test',
        'departement' => 'RH',
        'statut' => 'actif',
    ]);

    $user = app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    expect($user->hasRole('rh'))->toBeTrue();
});

test('le role controle est assigne pour le departement controle', function () {
    $this->seed(RoleSeeder::class);

    config(['cofina.provision_user_on_profil_create' => true]);

    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Omar',
        'nom' => 'Fall',
        'email' => 'omar.fall.controle@cofina.test',
        'departement' => 'CONTROLE',
        'statut' => 'actif',
    ]);

    $user = app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    expect($user->hasRole('controle'))->toBeTrue();
});

test('le role conformite est assigne pour le departement conformite', function () {
    $this->seed(RoleSeeder::class);

    config(['cofina.provision_user_on_profil_create' => true]);

    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Awa',
        'nom' => 'Ba',
        'email' => 'awa.ba.conformite@cofina.test',
        'departement' => 'CONFORMITE',
        'statut' => 'actif',
    ]);

    $user = app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    expect($user->hasRole('conformite'))->toBeTrue();
});

test('le role metier est assigne pour les autres departements', function () {
    $this->seed(RoleSeeder::class);

    config(['cofina.provision_user_on_profil_create' => true]);

    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Ibra',
        'nom' => 'Gueye',
        'email' => 'ibra.gueye.exploit@cofina.test',
        'departement' => 'EXPLOITATION',
        'statut' => 'actif',
    ]);

    $user = app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

    expect($user->hasRole('metier'))->toBeTrue();
});
