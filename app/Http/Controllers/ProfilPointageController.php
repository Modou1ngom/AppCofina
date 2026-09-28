<?php

namespace App\Http\Controllers;

use App\Models\Profil;
use App\Services\ProfilPointageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfilPointageController extends Controller
{
    public function index(): Response
    {
        $profils = Profil::query()
            ->where('pointage_statut', Profil::POINTAGE_EN_ATTENTE)
            ->latest()
            ->get()
            ->map(fn (Profil $profil) => [
                'id' => $profil->id,
                'matricule' => $profil->matricule,
                'prenom' => $profil->prenom,
                'nom' => $profil->nom,
                'fonction' => $profil->fonction,
                'departement' => $profil->departement,
                'email' => $profil->email,
                'site' => $profil->site,
                'date_entree' => $profil->date_entree?->format('d/m/Y'),
            ])
            ->values();

        $user = Auth::user();

        return Inertia::render('profils/Pointage', [
            'profils' => $profils,
            'peutConfirmer' => $user !== null && ($user->isAdmin() || $user->isExecuteurIt()),
        ]);
    }

    public function confirmer(Profil $profil, ProfilPointageService $pointage): RedirectResponse
    {
        $user = Auth::user();

        if ($user === null || (! $user->isAdmin() && ! $user->isExecuteurIt())) {
            abort(403, 'Seul l\'IT peut confirmer la prise en charge du pointage.');
        }

        if ($profil->pointage_statut !== Profil::POINTAGE_EN_ATTENTE) {
            return redirect()
                ->route('profils.pointage')
                ->with('error', 'Ce collaborateur n\'est plus en attente de pointage.');
        }

        $pointage->confirmer($profil, $user);

        return redirect()
            ->route('profils.pointage')
            ->with('success', sprintf(
                '%s %s est enregistré sur le pointage.',
                $profil->prenom,
                $profil->nom,
            ));
    }
}
