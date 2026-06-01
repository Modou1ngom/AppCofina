<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\User;

final class MobileUserPresenter
{
    /**
     * @return array{id: int, name: string, full_name: string, fullName: string, email: string, matricule: string|null, avatar_url: null, avatarUrl: null}
     */
    public static function profile(User $user): array
    {
        $user->profilCollaborateurAssocie()->loadMissing('profil');
        $profil = $user->profil;
        $full = $user->name;
        if ($profil !== null) {
            $fromProfil = trim(implode(' ', array_filter([(string) $profil->prenom, (string) $profil->nom])));
            if ($fromProfil !== '') {
                $full = $fromProfil;
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'full_name' => $full,
            'fullName' => $full,
            'email' => $user->email,
            'matricule' => $profil?->matricule,
            'avatar_url' => null,
            'avatarUrl' => null,
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    public static function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
