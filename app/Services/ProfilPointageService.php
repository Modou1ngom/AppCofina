<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Models\Profil;
use App\Models\ProfilMouvement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class ProfilPointageService
{
    /**
     * @return Collection<int, User>
     */
    public function destinatairesIt(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('roles', fn ($roles) => $roles->whereIn('slug', ['it', 'executeur_it']))
                    ->orWhereHas('profil.roles', fn ($roles) => $roles->whereIn('slug', ['it', 'executeur_it']))
                    ->orWhereHas('profil', function ($profil) {
                        $profil->where(function ($q) {
                            foreach (['it', 'informatique', 'technique'] as $mot) {
                                $q->orWhereRaw('LOWER(COALESCE(departement, "")) LIKE ?', ["%{$mot}%"])
                                    ->orWhereRaw('LOWER(COALESCE(fonction, "")) LIKE ?', ["%{$mot}%"]);
                            }
                        });
                    });
            })
            ->with('profil')
            ->get()
            ->filter(fn (User $user) => $user->isExecuteurIt())
            ->unique('id')
            ->values();
    }

    /**
     * Notifie l'IT qu'un collaborateur vient d'être enrôlé et doit être créé sur le pointage.
     */
    public function notifierDemande(Profil $profil, ?User $demandeur = null): bool
    {
        $destinataires = $this->destinatairesIt()
            ->reject(fn (User $user) => $this->memeEmail($user->email, $profil->email))
            ->values();

        if ($destinataires->isEmpty()) {
            Log::channel('single')->warning('Notification pointage non envoyée : aucun utilisateur IT actif', [
                'profil_id' => $profil->id,
            ]);

            return false;
        }

        $data = $this->donneesProfil($profil, $demandeur?->name);

        $emails = $destinataires
            ->pluck('email')
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            Log::channel('single')->warning('Notification pointage non envoyée : aucun e-mail IT', [
                'profil_id' => $profil->id,
            ]);

            return false;
        }

        $this->envoyerEmail(
            'Nouveau staff à enregistrer sur le pointage',
            $emails,
            'emails.profils.pointage-demande',
            $data,
        );

        return true;
    }

    public function confirmer(Profil $profil, User $it): void
    {
        $profil->forceFill([
            'pointage_statut' => Profil::POINTAGE_PRIS_EN_CHARGE,
            'pointage_confirme_at' => now(),
            'pointage_confirme_par' => $it->id,
        ])->save();

        $this->notifierConfirmation($profil, $it);
    }

    private function notifierConfirmation(Profil $profil, User $it): void
    {
        $createur = $profil->mouvements()
            ->where('type', ProfilMouvement::TYPE_ARRIVEE)
            ->latest('id')
            ->first()
            ?->createur;

        if ($createur === null || blank($createur->email) || $createur->id === $it->id) {
            return;
        }

        $this->envoyerEmail(
            'Pointage pris en charge',
            $createur->email,
            'emails.profils.pointage-confirme',
            [
                ...$this->donneesProfil($profil, $createur->name),
                'it' => $it->name,
                'url' => URL::route('profils.show', $profil),
            ],
        );
    }

    /**
     * @param  string|list<string>  $to
     * @param  array<string, string>  $data
     */
    private function envoyerEmail(string $subject, string|array $to, string $view, array $data): void
    {
        try {
            SendEmail::dispatchSync($subject, $to, [], [], $view, $data);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Échec envoi email pointage', [
                'subject' => $subject,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function donneesProfil(Profil $profil, ?string $demandeur): array
    {
        return [
            'prenom' => (string) $profil->prenom,
            'nom' => (string) $profil->nom,
            'matricule' => (string) $profil->matricule,
            'fonction' => (string) ($profil->fonction ?? ''),
            'departement' => (string) ($profil->departement ?? ''),
            'site' => (string) ($profil->site ?? ''),
            'email' => (string) ($profil->email ?? ''),
            'date_entree' => $profil->date_entree?->format('d/m/Y') ?? '',
            'demandeur' => (string) ($demandeur ?? ''),
            'url' => URL::route('profils.pointage'),
        ];
    }

    private function memeEmail(?string $a, ?string $b): bool
    {
        $a = strtolower(trim((string) $a));
        $b = strtolower(trim((string) $b));

        return $a !== '' && $a === $b;
    }
}
