<?php

namespace App\Http\Controllers;

use App\Models\SigPersonneLiee;
use App\Models\SigStaff;
use App\Services\SigOracleReportGroupeService;
use App\Services\SigSiLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SigDetectionAutomatiqueController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,conformite');
    }

    public function index(Request $request, SigOracleReportGroupeService $oracle): Response
    {
        $rows = $oracle->detectionStaffClients();

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, function (array $r) use ($needle) {
                $hay = mb_strtolower(implode(' ', [
                    $r['nom_staff'] ?? '',
                    $r['matricule_staff'] ?? '',
                    $r['numero_piece_staff'] ?? '',
                    $r['nom_personne_liee'] ?? '',
                    $r['matricule_personnel_lie'] ?? '',
                    $r['telephone_staff'] ?? '',
                    $r['type_liaison'] ?? '',
                ]));

                return str_contains($hay, $needle);
            }));
        }

        $refs = collect($rows)->pluck('matricule_staff')->filter()->unique()->values()->all();
        $staffLocaux = SigStaff::query()
            ->where(function ($q) use ($refs) {
                $q->whereIn('numero_client_si', $refs)->orWhereIn('reference', $refs);
            })
            ->with('personnesLiees:id,numero_client')
            ->get(['id', 'reference', 'numero_client_si', 'prenom', 'nom']);

        $staffByCle = [];
        foreach ($staffLocaux as $s) {
            foreach (array_filter([trim((string) $s->numero_client_si), trim((string) $s->reference)]) as $cle) {
                $staffByCle[$cle] = $s;
            }
        }

        $lignes = array_map(function (array $r) use ($staffByCle) {
            $cle = trim((string) ($r['matricule_staff'] ?? ''));
            $client = trim((string) ($r['matricule_personnel_lie'] ?? ''));
            $local = $cle !== '' ? ($staffByCle[$cle] ?? null) : null;
            $r['staff_local_id'] = $local?->id;
            $r['deja_lie'] = false;
            if ($local && $client !== '') {
                $r['deja_lie'] = $local->personnesLiees
                    ->contains(fn (SigPersonneLiee $p) => trim((string) $p->numero_client) === $client);
            }

            return $r;
        }, $rows);

        $counts = [
            'total' => count($lignes),
            'a_lier' => count(array_filter($lignes, fn (array $r) => empty($r['deja_lie']))),
            'deja_lie' => count(array_filter($lignes, fn (array $r) => ! empty($r['deja_lie']))),
        ];

        $action = trim((string) $request->get('action', ''));
        if (! in_array($action, ['a_lier', 'deja_lie'], true)) {
            $action = '';
        }
        if ($action === 'a_lier') {
            $lignes = array_values(array_filter($lignes, fn (array $r) => empty($r['deja_lie'])));
        } elseif ($action === 'deja_lie') {
            $lignes = array_values(array_filter($lignes, fn (array $r) => ! empty($r['deja_lie'])));
        }

        return Inertia::render('suivi-signature/DetectionAutomatique', [
            'lignes' => $lignes,
            'filters' => [
                'search' => $search,
                'action' => $action,
            ],
            'counts' => $counts,
            'total' => count($lignes),
        ]);
    }

    /**
     * Associe le client détecté au staff (crée la fiche staff et/ou personne liée depuis le SI si besoin).
     */
    public function lier(Request $request, SigSiLookupService $siLookup): RedirectResponse
    {
        $validated = $request->validate([
            'matricule_staff' => 'required|string|max:100',
            'matricule_personnel_lie' => 'required|string|max:100',
            'type_relation' => ['required', 'string', 'max:255', \App\Support\SigTypeRelation::rule()],
            'complement' => 'nullable|string|max:255',
            'classe' => 'nullable|integer|min:1|max:4',
            'nom_staff' => 'nullable|string|max:255',
            'telephone_staff' => 'nullable|string|max:50',
            'type_piece_staff' => 'nullable|string|max:50',
            'numero_piece_staff' => 'nullable|string|max:100',
            'encours_staff' => 'nullable|numeric|min:0',
            'nom_personne_liee' => 'nullable|string|max:255',
        ]);

        $matriculeStaff = trim($validated['matricule_staff']);
        $matriculeClient = trim($validated['matricule_personnel_lie']);
        $typeRelation = $validated['type_relation'];
        $complement = trim((string) ($validated['complement'] ?? ''));
        if ($complement !== '') {
            $typeRelation .= ' — '.$complement;
        }
        $classe = max(1, min(4, (int) ($validated['classe'] ?? 2)));

        $siStaff = $siLookup->lookup($matriculeStaff, 'personnel');
        if ($siStaff === null) {
            $siStaff = [
                'matricule' => $matriculeStaff,
                'type_client' => 'personnel',
                'prenom' => null,
                'nom' => null,
                'prenom_nom' => trim((string) ($validated['nom_staff'] ?? '')) ?: $matriculeStaff,
                'adresse' => null,
                'telephone' => $validated['telephone_staff'] ?? null,
                'email' => null,
                'piece_type' => $validated['type_piece_staff'] ?? 'CNI',
                'piece_numero' => $validated['numero_piece_staff'] ?? null,
                'encours_total' => isset($validated['encours_staff']) ? (float) $validated['encours_staff'] : null,
            ];
        }

        try {
            $staff = SigStaff::ensureFromSiData($siStaff);
        } catch (\Throwable $e) {
            return back()->with('error', 'Impossible de créer la fiche staff : '.$e->getMessage());
        }

        if ($staff->liaisonPersonnesLieesBloquee()) {
            return back()->with('error', 'Seuil d’encours dépassé : liaison impossible pour ce staff.');
        }

        if ($staff->personnesLiees()->where('numero_client', $matriculeClient)->exists()) {
            return back()->with('success', 'Cette personne est déjà liée à ce staff.');
        }

        $siData = $siLookup->lookup($matriculeClient, 'personnel');
        if ($siData === null) {
            $siData = $siLookup->lookup($matriculeClient, 'entreprise');
        }
        if ($siData === null) {
            $siData = [
                'matricule' => $matriculeClient,
                'type_client' => 'personnel',
                'prenom' => null,
                'nom' => null,
                'raison_sociale' => null,
                'prenom_nom' => trim((string) ($validated['nom_personne_liee'] ?? '')) ?: $matriculeClient,
                'adresse' => null,
                'telephone' => null,
                'email' => null,
                'piece_type' => 'CNI',
                'piece_numero' => null,
            ];
        }

        try {
            $personne = SigPersonneLiee::ensureFromSiData($siData);
        } catch (\Throwable $e) {
            return back()->with('error', 'Impossible de créer la fiche personne liée : '.$e->getMessage());
        }

        if ($staff->personnesLiees()->whereKey($personne->id)->exists()) {
            return back()->with('success', 'Cette personne est déjà liée à ce staff.');
        }

        $staff->refresh();
        $sumLiees = (float) $staff->personnesLiees()->sum('encours_credit');
        $totalProjete = (float) $staff->encours_staff_si + $sumLiees + (float) $personne->encours_credit;
        $fp = (float) ($staff->fondsPropresReference() ?? 0);
        $seuil = \App\Models\SigParametre::current()->seuilTauxPct();
        if ($fp > 0 && ($totalProjete / $fp) * 100 > $seuil) {
            return back()->with('error', 'Liaison refusée : le seuil d’encours / fonds propres serait dépassé.');
        }

        $staff->personnesLiees()->attach($personne->id, [
            'type_relation' => $typeRelation,
            'classe' => $classe,
        ]);
        $staff->synchroniserEncoursTotaux();

        return back()->with(
            'success',
            sprintf(
                'Personne %s liée au staff %s %s.',
                $matriculeClient,
                $staff->prenom,
                $staff->nom
            )
        );
    }
}
