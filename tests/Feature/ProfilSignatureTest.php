<?php

use App\Models\Profil;
use App\Services\ProfilSignatureService;

test('la première signature est enregistrée sur le profil', function () {
    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Awa',
        'nom' => 'Sarr',
        'statut' => 'actif',
    ]);

    $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    app(ProfilSignatureService::class)->attachToProfilAfterFirstSignature($profil, $dataUrl);

    $profil->refresh();
    expect($profil->signature)->toBe($dataUrl)
        ->and($profil->signature_enregistree_at)->not->toBeNull();
});

test('resolve utilise la signature du profil si demandé', function () {
    $dataUrl = 'data:image/png;base64,abc';
    $profil = Profil::query()->create([
        'matricule' => Profil::generateMatricule(),
        'prenom' => 'Modou',
        'nom' => 'Ngom',
        'signature' => $dataUrl,
        'statut' => 'actif',
    ]);

    $resolved = app(ProfilSignatureService::class)->resolveForValidation($profil, null, true);

    expect($resolved)->toBe($dataUrl);
});
