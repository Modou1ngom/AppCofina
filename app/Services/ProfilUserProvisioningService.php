<?php

namespace App\Services;

use App\Models\Profil;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfilUserProvisioningService
{
    public function isEnabled(): bool
    {
        return (bool) config('cofina.provision_user_on_profil_create', true);
    }

    /**
     * Crée les comptes User pour plusieurs profils (un seul hash Bcrypt).
     *
     * @param  iterable<int, Profil>  $profils
     */
    public function provisionMany(iterable $profils): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $passwordHash = Hash::make((string) config('cofina.default_user_password', 'Cofina@2025'));
        $created = 0;

        foreach ($profils as $profil) {
            if ($this->provisionUserForProfil($profil, $passwordHash) !== null) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Crée ou met à jour le compte User lié au profil (liaison par e-mail).
     */
    public function provisionUserForProfil(Profil $profil, ?string $passwordHash = null): ?User
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $email = strtolower(trim((string) $profil->email));
        if ($email === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if ($user === null) {
            $user = User::create([
                'name' => $this->displayName($profil),
                'email' => $email,
                'password' => $passwordHash ?? Hash::make((string) config('cofina.default_user_password', 'Cofina@2025')),
                'must_change_password' => true,
                'is_active' => $this->profilIsActive($profil),
            ]);
        } else {
            $this->syncUserFromProfil($user, $profil);
        }

        if (strtolower(trim((string) $profil->email)) !== $email) {
            $profil->update(['email' => $email]);
        }

        $this->attachFilialeFromProfil($user, $profil);

        if ($profil->roles()->exists()) {
            $user->roles()->sync($profil->roles()->pluck('role_id'));
        }

        return $user;
    }

    public function syncUserFromProfil(User $user, Profil $profil): void
    {
        $user->update([
            'name' => $this->displayName($profil),
            'is_active' => $this->profilIsActive($profil),
        ]);

        $this->attachFilialeFromProfil($user, $profil);

        if ($profil->roles()->exists()) {
            $user->roles()->sync($profil->roles()->pluck('role_id'));
        }
    }

    private function displayName(Profil $profil): string
    {
        return trim("{$profil->prenom} {$profil->nom}");
    }

    private function profilIsActive(Profil $profil): bool
    {
        return ($profil->statut ?? 'actif') === 'actif';
    }

    private function attachFilialeFromProfil(User $user, Profil $profil): void
    {
        if ($profil->filiale_id) {
            $user->filiales()->syncWithoutDetaching([(int) $profil->filiale_id]);
        }
    }
}
