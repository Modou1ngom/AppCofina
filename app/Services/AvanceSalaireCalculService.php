<?php

namespace App\Services;

use App\Models\AvanceSalaireDemande;
use App\Models\Profil;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AvanceSalaireCalculService
{
    /**
     * @return array{eligible: bool, messages: list<string>, montant_max: float, plafond_pct: float}
     */
    public function evaluerEligibilite(
        Profil $profil,
        float $montant,
        int $dureeMois,
        float $salaireNet,
        bool $salaireDomicilie,
        ?float $plafondPct = null,
        ?int $excludeDemandeId = null,
        ?float $plafondMontantBaremeMax = null,
        ?int $dureeMaxMoisBareme = null,
    ): array {
        $plafondPct = $plafondPct ?? (float) config('avance_salaire.plafond_pct_defaut', 30);
        $montantMax = $salaireNet > 0
            ? round($salaireNet * ($plafondPct / 100), 2)
            : round((float) ($plafondMontantBaremeMax ?? 0), 2);
        if ($plafondMontantBaremeMax !== null) {
            $montantMax = $salaireNet > 0
                ? min($montantMax, round($plafondMontantBaremeMax, 2))
                : round($plafondMontantBaremeMax, 2);
        }
        $messages = [];
        $eligible = true;

        // Le statut de domiciliation et l'ancienneté restent saisis, mais ne bloquent plus l'éligibilité.

        if ($montant > $montantMax + 0.0001) {
            $eligible = false;
            $messages[] = $plafondMontantBaremeMax !== null
                ? 'Le montant demandé dépasse le plafond autorisé (pourcentage du net et/ou plafond barème catégorie).'
                : 'Le montant demandé dépasse le plafond autorisé ('.$plafondPct.' % du salaire net).';
        }

        $dMin = (int) config('avance_salaire.duree_mois_min', 1);
        $dMax = $dureeMaxMoisBareme !== null
            ? max($dMin, $dureeMaxMoisBareme)
            : (int) config('avance_salaire.duree_mois_max', 6);
        if ($dureeMois < $dMin || $dureeMois > $dMax) {
            $eligible = false;
            $messages[] = "La durée doit être comprise entre {$dMin} et {$dMax} mois.";
        }

        if (AvanceSalaireDemande::query()
            ->activesPourProfile($profil->id, $excludeDemandeId)
            ->exists()) {
            $eligible = false;
            $messages[] = 'Une avance est déjà en cours ou approuvée pour ce profil (une seule avance active).';
        }

        if ($eligible && $messages === []) {
            $messages[] = 'Éligible selon les règles paramétrées.';
        }

        return [
            'eligible' => $eligible,
            'messages' => $messages,
            'montant_max' => $montantMax,
            'plafond_pct' => $plafondPct,
        ];
    }

    /**
     * @return array{mensualite: float, date_fin: CarbonInterface, tableau: list<array{numero: int, date_echeance: string, capital_restant: float, interets: float, amortissement: float, mensualite: float}>}
     */
    public function simuler(
        float $montant,
        int $dureeMois,
        CarbonInterface $premiereEcheance,
        float $tauxAnnuelPct,
    ): array {
        $n = max(1, $dureeMois);
        $tauxMensuel = ($tauxAnnuelPct / 100) / 12;
        $date = Carbon::instance($premiereEcheance)->startOfDay();

        if ($tauxMensuel <= 0) {
            $mensualite = round($montant / $n, 2);
            $tableau = [];
            $restant = $montant;
            for ($i = 1; $i <= $n; $i++) {
                $debut = round($restant, 2);
                $amort = $i === $n ? round($restant, 2) : $mensualite;
                $ligne = round($amort, 2);
                $restant = round($restant - $amort, 2);
                $tableau[] = [
                    'numero' => $i,
                    'date_echeance' => $date->format('Y-m-d'),
                    'capital_restant_debut' => $debut,
                    'interets' => 0.0,
                    'amortissement' => $ligne,
                    'mensualite' => $ligne,
                ];
                $date = $date->copy()->addMonth();
            }
            $dateFin = Carbon::parse($tableau[$n - 1]['date_echeance']);

            return [
                'mensualite' => $mensualite,
                'date_fin' => $dateFin,
                'tableau' => $tableau,
            ];
        }

        $pow = pow(1 + $tauxMensuel, $n);
        $mensualite = round($montant * ($tauxMensuel * $pow) / ($pow - 1), 2);
        $tableau = [];
        $restant = $montant;

        for ($i = 1; $i <= $n; $i++) {
            $debut = round($restant, 2);
            $interets = round($restant * $tauxMensuel, 2);
            $amort = $i === $n ? round($restant, 2) : round($mensualite - $interets, 2);
            $ligne = round($amort + $interets, 2);
            $restant = round($restant - $amort, 2);
            $tableau[] = [
                'numero' => $i,
                'date_echeance' => $date->format('Y-m-d'),
                'capital_restant_debut' => $debut,
                'interets' => $interets,
                'amortissement' => $amort,
                'mensualite' => $ligne,
            ];
            $date = $date->copy()->addMonth();
        }

        $dateFin = Carbon::parse($tableau[$n - 1]['date_echeance']);

        return [
            'mensualite' => $mensualite,
            'date_fin' => $dateFin,
            'tableau' => $tableau,
        ];
    }
}
