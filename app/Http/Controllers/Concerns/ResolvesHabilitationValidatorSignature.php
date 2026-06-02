<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Profil;
use App\Services\ProfilSignatureService;
use Illuminate\Http\Request;

trait ResolvesHabilitationValidatorSignature
{
    protected function validatorProfilSignature(?Profil $profil): ?string
    {
        return $profil?->signature;
    }

    protected function resolveHabilitationSignature(
        Request $request,
        ?Profil $profil,
        string $field,
        string $useRegisteredField = 'use_registered_signature'
    ): ?string {
        return app(ProfilSignatureService::class)->resolveForValidation(
            $profil,
            $request->input($field),
            $request->boolean($useRegisteredField)
        );
    }
}
