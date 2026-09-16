<?php

namespace App\Services;

use App\Models\Profil;
use App\Models\ProfilMouvement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfilMouvementService
{
    public function __construct(
        private ProfilUserProvisioningService $provisioning,
    ) {}

    public function enregistrerArrivee(
        Profil $profil,
        ?CarbonInterface $dateEffet = null,
        ?string $motif = null,
        ?User $acteur = null,
    ): ProfilMouvement {
        $dateEffet ??= $profil->date_entree ?? $profil->created_at ?? now();

        if (! $profil->date_entree) {
            $profil->date_entree = ($profil->created_at ?? $dateEffet)->toDateString();
            $profil->save();
        }

        return $this->creerMouvement(
            $profil,
            ProfilMouvement::TYPE_ARRIVEE,
            $dateEffet,
            $this->snapshot($profil),
            $this->snapshot($profil),
            $motif ?? 'Arrivée / enrôlement',
            $acteur,
        );
    }

    public function enregistrerDepart(
        Profil $profil,
        CarbonInterface $dateEffet,
        string $motif,
        ?User $acteur = null,
    ): ProfilMouvement {
        if (($profil->statut ?? 'actif') !== 'actif') {
            throw ValidationException::withMessages([
                'profil_id' => 'Ce collaborateur est déjà inactif. Un départ a déjà été enregistré.',
            ]);
        }

        $avant = $this->snapshot($profil);

        return DB::transaction(function () use ($profil, $dateEffet, $motif, $acteur, $avant) {
            $profil->update([
                'statut' => 'inactif',
                'date_sortie' => $dateEffet->toDateString(),
                'motif_depart' => $motif,
            ]);
            $profil->refresh();

            $this->provisioning->provisionUserForProfil($profil);

            return $this->creerMouvement(
                $profil,
                ProfilMouvement::TYPE_DEPART,
                $dateEffet,
                $avant,
                $this->snapshot($profil),
                $motif,
                $acteur,
            );
        });
    }

    /**
     * @param  array{fonction?: ?string, departement?: ?string, site?: ?string, n_plus_1_id?: int|null}  $nouveau
     */
    public function enregistrerChangementPoste(
        Profil $profil,
        array $nouveau,
        CarbonInterface $dateEffet,
        ?string $motif = null,
        ?User $acteur = null,
    ): ProfilMouvement {
        if (($profil->statut ?? 'actif') !== 'actif') {
            throw ValidationException::withMessages([
                'profil_id' => 'Impossible de changer le poste d’un collaborateur inactif.',
            ]);
        }

        $avant = $this->snapshot($profil);
        $apres = [
            'fonction' => array_key_exists('fonction', $nouveau) ? $this->nullableString($nouveau['fonction'] ?? null) : $avant['fonction'],
            'departement' => array_key_exists('departement', $nouveau) ? $this->nullableString($nouveau['departement'] ?? null) : $avant['departement'],
            'site' => array_key_exists('site', $nouveau) ? $this->nullableString($nouveau['site'] ?? null) : $avant['site'],
            'n_plus_1_id' => array_key_exists('n_plus_1_id', $nouveau) ? $this->nullableInt($nouveau['n_plus_1_id'] ?? null) : $avant['n_plus_1_id'],
        ];

        if ($this->snapshotsIdentiques($avant, $apres)) {
            throw ValidationException::withMessages([
                'fonction' => 'Aucun changement de poste, d’agence ou de N+1 n’a été détecté.',
            ]);
        }

        if ($apres['n_plus_1_id'] === $profil->id) {
            throw ValidationException::withMessages([
                'n_plus_1_id' => 'Un collaborateur ne peut pas être son propre N+1.',
            ]);
        }

        return DB::transaction(function () use ($profil, $avant, $apres, $dateEffet, $motif, $acteur) {
            $nPlus1Changed = $avant['n_plus_1_id'] !== $apres['n_plus_1_id'];

            $profil->update([
                'fonction' => $apres['fonction'],
                'departement' => $apres['departement'],
                'site' => $apres['site'],
                'n_plus_1_id' => $apres['n_plus_1_id'],
                'n_plus_2_id' => $this->resoudreNPlus2($apres['n_plus_1_id'], $profil->id),
            ]);
            $profil->refresh();

            $this->provisioning->provisionUserForProfil($profil);

            if ($nPlus1Changed) {
                $this->recalculerNPlus2DesSubordonnes($profil);
            }

            return $this->creerMouvement(
                $profil,
                ProfilMouvement::TYPE_CHANGEMENT_POSTE,
                $dateEffet,
                $avant,
                $this->snapshot($profil),
                $motif,
                $acteur,
            );
        });
    }

    /**
     * Enregistre un mouvement si le formulaire d’édition générique a modifié le poste ou le statut.
     *
     * @param  array<string, mixed>  $avant
     */
    public function enregistrerDepuisMiseAJourProfil(
        Profil $profil,
        array $avant,
        ?User $acteur = null,
    ): void {
        $profil->refresh();
        $apres = $this->snapshot($profil);
        $statutAvant = (string) ($avant['statut'] ?? 'actif');
        $statutApres = (string) ($profil->statut ?? 'actif');

        if ($statutAvant === 'actif' && $statutApres === 'inactif') {
            if (! $profil->date_sortie) {
                $profil->date_sortie = now()->toDateString();
                $profil->save();
            }

            $this->creerMouvement(
                $profil,
                ProfilMouvement::TYPE_DEPART,
                $profil->date_sortie ?? now(),
                $avant,
                $apres,
                $profil->motif_depart ?: 'Mise à jour du statut (inactif)',
                $acteur,
            );

            return;
        }

        if ($this->snapshotsIdentiques($avant, $apres)) {
            return;
        }

        $this->creerMouvement(
            $profil,
            ProfilMouvement::TYPE_CHANGEMENT_POSTE,
            now(),
            $avant,
            $apres,
            'Mise à jour du profil',
            $acteur,
        );
    }

    /**
     * @return array{fonction: ?string, departement: ?string, site: ?string, n_plus_1_id: ?int, statut: ?string}
     */
    public function snapshot(Profil $profil): array
    {
        return [
            'fonction' => $this->nullableString($profil->getRawOriginal('fonction')),
            'departement' => $this->nullableString($profil->getRawOriginal('departement')),
            'site' => $this->nullableString($profil->getRawOriginal('site')),
            'n_plus_1_id' => $this->nullableInt($profil->n_plus_1_id),
            'statut' => $this->nullableString($profil->getRawOriginal('statut') ?? $profil->statut),
        ];
    }

    /**
     * @param  array{fonction: ?string, departement: ?string, site: ?string, n_plus_1_id: ?int}  $avant
     * @param  array{fonction: ?string, departement: ?string, site: ?string, n_plus_1_id: ?int}  $apres
     */
    private function creerMouvement(
        Profil $profil,
        string $type,
        CarbonInterface $dateEffet,
        array $avant,
        array $apres,
        ?string $motif,
        ?User $acteur,
    ): ProfilMouvement {
        return ProfilMouvement::query()->create([
            'profil_id' => $profil->id,
            'type' => $type,
            'date_effet' => $dateEffet->toDateString(),
            'fonction_avant' => $avant['fonction'] ?? null,
            'fonction_apres' => $apres['fonction'] ?? null,
            'departement_avant' => $avant['departement'] ?? null,
            'departement_apres' => $apres['departement'] ?? null,
            'site_avant' => $avant['site'] ?? null,
            'site_apres' => $apres['site'] ?? null,
            'n_plus_1_id_avant' => $avant['n_plus_1_id'] ?? null,
            'n_plus_1_id_apres' => $apres['n_plus_1_id'] ?? null,
            'motif' => $this->nullableString($motif),
            'created_by' => $acteur?->id,
        ]);
    }

    private function recalculerNPlus2DesSubordonnes(Profil $profil): void
    {
        $nPlus2Id = $profil->n_plus_1_id;

        Profil::query()
            ->where('n_plus_1_id', $profil->id)
            ->update(['n_plus_2_id' => $nPlus2Id]);
    }

    private function resoudreNPlus2(?int $nPlus1Id, int $profilId): ?int
    {
        if ($nPlus1Id === null || $nPlus1Id === $profilId) {
            return null;
        }

        $nPlus1 = Profil::query()->find($nPlus1Id);
        if ($nPlus1?->n_plus_1_id && $nPlus1->n_plus_1_id !== $nPlus1Id) {
            return (int) $nPlus1->n_plus_1_id;
        }

        return null;
    }

    /**
     * @param  array{fonction?: ?string, departement?: ?string, site?: ?string, n_plus_1_id?: ?int}  $a
     * @param  array{fonction?: ?string, departement?: ?string, site?: ?string, n_plus_1_id?: ?int}  $b
     */
    private function snapshotsIdentiques(array $a, array $b): bool
    {
        return ($a['fonction'] ?? null) === ($b['fonction'] ?? null)
            && ($a['departement'] ?? null) === ($b['departement'] ?? null)
            && ($a['site'] ?? null) === ($b['site'] ?? null)
            && ($a['n_plus_1_id'] ?? null) === ($b['n_plus_1_id'] ?? null);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
