<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Profil;
use App\Services\ProfilSignatureService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    protected function requireHabilitationSignature(
        Request $request,
        ?Profil $profil,
        string $field,
        string $useRegisteredField = 'use_registered_signature'
    ): string {
        $signature = $this->resolveHabilitationSignature($request, $profil, $field, $useRegisteredField);

        if ($signature === null || $signature === '') {
            throw ValidationException::withMessages([
                $field => 'La signature est obligatoire pour cette validation.',
            ]);
        }

        return $signature;
    }
}
