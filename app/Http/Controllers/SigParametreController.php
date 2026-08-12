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
        $params->update([
            'fonds_propres' => $validated['fonds_propres'] !== null && $validated['fonds_propres'] !== ''
                ? $validated['fonds_propres']
                : null,
            'seuil_taux_pct' => $seuil,
            'alerte_taux_pct' => $alerte,
        ]);

        // Recalcule les transitions conformité sur les fiches actives de cet environnement.
        SigStaff::query()
            ->where('statut', 'actif')
            ->orderBy('id')
            ->chunkById(50, function ($chunk) {
                foreach ($chunk as $staff) {
                    $staff->synchroniserEncoursTotaux();
                }
            });

        return back()->with('success', 'Paramètres de conformité enregistrés pour cet environnement.');
    }
}
