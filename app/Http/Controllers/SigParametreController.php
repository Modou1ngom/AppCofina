<?php

namespace App\Http\Controllers;

use App\Helpers\FilialeHelper;
use App\Models\Filiale;
use App\Models\SigParametre;
use App\Models\SigStaff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SigParametreController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isConformite())) {
            abort(403);
        }

        $filialeId = FilialeHelper::getCurrentFilialeId();
        $filialeNom = $filialeId
            ? Filiale::query()->whereKey($filialeId)->value('nom')
            : null;

        return Inertia::render('suivi-signature/Parametrage', [
            'parametres' => SigParametre::current()->toVueArray(),
            'environnement' => $filialeNom,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isConformite())) {
            abort(403);
        }

        $validated = $request->validate([
            'fonds_propres' => 'nullable|numeric|min:0',
            'seuil_taux_pct' => 'required|numeric|min:0.01|max:100',
            'alerte_taux_pct' => 'required|numeric|min:0|max:100',
        ], [
            'alerte_taux_pct.required' => 'Le seuil d’alerte est obligatoire.',
            'seuil_taux_pct.required' => 'Le seuil de dépassement est obligatoire.',
        ]);

        $alerte = (float) $validated['alerte_taux_pct'];
        $seuil = (float) $validated['seuil_taux_pct'];
        if ($alerte > $seuil) {
            return back()
                ->withErrors(['alerte_taux_pct' => 'Le seuil d’alerte doit être inférieur ou égal au seuil de dépassement.'])
                ->withInput();
        }

        $params = SigParametre::current();
        $ancienFp = $params->fondsPropresReference();
        $nouveauFp = ($validated['fonds_propres'] !== null && $validated['fonds_propres'] !== '')
            ? (float) $validated['fonds_propres']
            : null;
        if ($nouveauFp !== null && $nouveauFp <= 0) {
            $nouveauFp = null;
        }

        $fpChanged = $this->fondsPropresOntChange($ancienFp, $nouveauFp);

        $params->update([
            'fonds_propres' => $nouveauFp,
            'seuil_taux_pct' => $seuil,
            'alerte_taux_pct' => $alerte,
        ]);

        // Recalcule les transitions conformité ; si FP changés, photographie chaque fiche active.
        SigStaff::query()
            ->where('statut', 'actif')
            ->orderBy('id')
            ->chunkById(50, function ($chunk) use ($fpChanged, $ancienFp, $nouveauFp, $user) {
                foreach ($chunk as $staff) {
                    /** @var SigStaff $staff */
                    $staff->synchroniserEncoursTotaux();
                    if ($fpChanged) {
                        $staff->photographierApresChangementFondsPropres(
                            $user?->id,
                            $ancienFp,
                            $nouveauFp
                        );
                    }
                }
            });

        $msg = 'Paramètres de conformité enregistrés pour cet environnement.';
        if ($fpChanged) {
            $msg .= ' Un snapshot a été enregistré dans le rapport de conformité pour chaque fiche active.';
        }

        return back()->with('success', $msg);
    }

    private function fondsPropresOntChange(?float $ancien, ?float $nouveau): bool
    {
        if ($ancien === null && $nouveau === null) {
            return false;
        }
        if ($ancien === null || $nouveau === null) {
            return true;
        }

        return round($ancien, 2) !== round($nouveau, 2);
    }
}
