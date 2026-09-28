<?php

use App\Models\Profil;
use App\Services\ProfilBulkImportService;

test('un matricule numerique 803 est remplace par M0803 a l import', function () {
    $profil = Profil::query()->create([
        'matricule' => '803',
        'prenom' => 'Dieynaba',
        'nom' => 'BA',
        'email' => 'ancienne.dieynaba@cofina.test',
        'statut' => 'actif',
    ]);

    $result = app(ProfilBulkImportService::class)->process(
        [
            ['BA', 'Dieynaba', 'M0803', '803', 'dieynaba.ba@cofinacorp.com'],
        ],
        [
            'nom' => 0,
            'prenom' => 1,
            'matricule' => 2,
            'matricule_sirh' => 3,
            'email' => 4,
        ],
        0,
        null,
    );

    $profil->refresh();

    expect($result['updated'])->toBe(1)
        ->and($result['created'])->toBe(0)
        ->and($profil->matricule)->toBe('M0803')
        ->and($profil->matricule_sirh)->toBe('803')
        ->and($profil->email)->toBe('dieynaba.ba@cofinacorp.com');
});

test('un ancien matricule est remplace par M0803 quand l e-mail correspond', function () {
    $profil = Profil::query()->create([
        'matricule' => 'M0007',
        'prenom' => 'Dieynaba',
        'nom' => 'BA',
        'email' => 'dieynaba.ba@cofinacorp.com',
        'statut' => 'actif',
    ]);

    app(ProfilBulkImportService::class)->process(
        [
            ['BA', 'Dieynaba', 'M0803', '803', 'dieynaba.ba@cofinacorp.com'],
        ],
        [
            'nom' => 0,
            'prenom' => 1,
            'matricule' => 2,
            'matricule_sirh' => 3,
            'email' => 4,
        ],
        0,
        null,
    );

    $profil->refresh();

    expect($profil->matricule)->toBe('M0803')
        ->and($profil->matricule_sirh)->toBe('803')
        ->and(Profil::query()->count())->toBe(1);
});
