<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\Profil;

/**
 * Récupération des fiches « client » depuis le SI.
 *
 * Si le proxy HTTP Python (SIG_ORACLE_HTTP_*) est activé, lecture via ce service ;
 * sinon Oracle direct (oci8 + SQL) si configuré ; sinon profils / agences en base locale.
 */
class SigSiLookupService
{
    public function __construct(
        private readonly SigOracleReportGroupeService $oracleReportGroupe
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function lookup(string $matricule, string $typeClient): ?array
    {
        $matricule = trim($matricule);
        if ($matricule === '') {
            return null;
        }

        return $typeClient === 'entreprise'
            ? $this->lookupEntreprise($matricule)
            : $this->lookupPersonnel($matricule);
    }

    /**
     * Personnes liées issues d’Oracle pour un matricule staff (liste vide si non configuré).
     *
     * @return list<array<string, mixed>>
     */
    public function personnesLieesSiPourMatricule(string $matricule): array
    {
        return $this->oracleReportGroupe->personnesLieesPourStaffMatricule(trim($matricule));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookupPersonnel(string $matricule): ?array
    {
        $fromOracle = $this->oracleReportGroupe->lookupPersonnel($matricule);
        if ($fromOracle !== null) {
            return $fromOracle;
        }

        $profil = Profil::query()->where('matricule', $matricule)->first();

        if (! $profil) {
            return null;
        }

        return [
            'matricule' => $profil->matricule,
            'type_client' => 'personnel',
            'profile_id' => $profil->id,
            'prenom' => $profil->prenom,
            'nom' => $profil->nom,
            'prenom_nom' => trim($profil->prenom.' '.$profil->nom),
            'adresse' => null,
            'genre' => null,
            'telephone' => $profil->telephone,
            'email' => $profil->email,
            'piece_type' => 'CNI',
            'piece_numero' => null,
            'agence' => $profil->site,
            'fonction' => $profil->fonction,
            'departement' => $profil->departement,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookupEntreprise(string $numeroClient): ?array
    {
        $agence = Agence::query()
            ->where('code_agent', $numeroClient)
            ->orWhere('nom', $numeroClient)
            ->first();

        if (! $agence) {
            return null;
        }

        return [
            'matricule' => $agence->code_agent,
            'type_client' => 'entreprise',
            'profile_id' => null,
            'prenom' => null,
            'nom' => null,
            'raison_sociale' => $agence->nom,
            'prenom_nom' => $agence->nom,
            'adresse' => $agence->description,
            'genre' => null,
            'telephone' => null,
            'email' => null,
            'piece_type' => 'RCCM',
            'piece_numero' => null,
            'agence' => $agence->nom,
            'fonction' => null,
            'departement' => null,
        ];
    }
}
