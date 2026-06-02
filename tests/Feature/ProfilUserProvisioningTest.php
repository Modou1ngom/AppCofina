<?php

use App\Models\Profil;
use App\Services\ProfilUserProvisioningService;
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
