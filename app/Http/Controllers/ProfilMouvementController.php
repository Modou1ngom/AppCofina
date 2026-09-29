<?php

namespace App\Http\Controllers;

use App\Exports\MouvementsExport;
use App\Models\Agence;
use App\Models\Departement;
use App\Models\Profil;
use App\Models\ProfilMouvement;
use App\Services\ProfilMouvementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfilMouvementController extends Controller
{
    public function __construct(
        private ProfilMouvementService $mouvements,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 15);
        $filters = $this->movementFilters($request);

        $mouvements = $this->filteredMouvements($user, $filters)
            ->with([
                'profil:id,nom,prenom,matricule,statut',
                'nPlus1Avant:id,nom,prenom,matricule',
                'nPlus1Apres:id,nom,prenom,matricule',
                'createur:id,name,email',
            ])
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ProfilMouvement $m) => $this->toListRow($m));

        return Inertia::render('profils/Mouvements/Index', [
            'mouvements' => $mouvements,
            'types' => collect(ProfilMouvement::typeLabels())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:arrivee,depart',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'search' => 'nullable|string|max:255',
        ]);

        $filters = $this->movementFilters($request);
        $query = $this->filteredMouvements(Auth::user(), $filters);
        $label = $validated['type'] === ProfilMouvement::TYPE_ARRIVEE ? 'arrivees' : 'departs';
        $fileName = $label.'_'.$validated['date_debut'].'_'.$validated['date_fin'].'.xlsx';

        return Excel::download(new MouvementsExport($query), $fileName);
    }

    /**
     * @return array{type: string, search: string, date_debut: string, date_fin: string}
     */
    private function movementFilters(Request $request): array
    {
        $type = (string) $request->get('type', '');
        if (! array_key_exists($type, ProfilMouvement::typeLabels())) {
            $type = '';
        }

        return [
            'type' => $type,
            'search' => trim((string) $request->get('search', '')),
            'date_debut' => (string) $request->get('date_debut', ''),
            'date_fin' => (string) $request->get('date_fin', ''),
        ];
    }

    /**
     * @param  array{type: string, search: string, date_debut: string, date_fin: string}  $filters
     * @return Builder<ProfilMouvement>
     */
    private function filteredMouvements(?\App\Models\User $user, array $filters): Builder
    {
        $query = ProfilMouvement::query();

        if ($user) {
            $profilIds = Profil::query();
            $user->applyProfilVisibilityScope($profilIds);
            $query->whereIn('profil_id', $profilIds->select('id'));
        } else {
            $query->whereRaw('0 = 1');
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if ($filters['date_debut'] !== '') {
            $query->whereDate('date_effet', '>=', $filters['date_debut']);
        }

        if ($filters['date_fin'] !== '') {
            $query->whereDate('date_effet', '<=', $filters['date_fin']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->whereHas('profil', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function createDepart(Request $request): Response
    {
        return Inertia::render('profils/Mouvements/Depart', [
            'profils' => $this->profilsActifsPourSelect(),
            'profilIdInitial' => $request->integer('profil_id') ?: null,
            'motifs' => $this->motifsDepart(),
        ]);
    }

    public function storeDepart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profil_id' => 'required|exists:profiles,id',
            'date_effet' => 'required|date',
            'motif' => 'required|string|max:255',
            'motif_libre' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $profil = Profil::query()->findOrFail($validated['profil_id']);

        if (! $user || ! $user->canAccessProfil($profil)) {
            abort(403, 'Vous n\'avez pas accès à ce profil.');
        }

        $motif = $validated['motif'] === 'Autre'
            ? trim((string) ($validated['motif_libre'] ?? ''))
            : $validated['motif'];

        if ($motif === '') {
            return back()->withErrors(['motif_libre' => 'Précisez le motif du départ.']);
        }

        $this->mouvements->enregistrerDepart(
            $profil,
            \Illuminate\Support\Carbon::parse($validated['date_effet']),
            $motif,
            $user,
        );

        return redirect()
            ->route('profils.mouvements.index')
            ->with('success', "Départ enregistré pour {$profil->prenom} {$profil->nom}. Le compte utilisateur a été désactivé.");
    }

    public function createChangementPoste(Request $request): Response
    {
        $user = Auth::user();
        $agencesQuery = Agence::where('actif', true);
        if ($user) {
            $user->applyFilialeScopeToQuery($agencesQuery);
        }

        return Inertia::render('profils/Mouvements/ChangementPoste', [
            'profils' => $this->profilsActifsPourSelect(),
            'hierarchie' => $this->profilsPourHierarchie(),
            'departements' => Departement::where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'agences' => $agencesQuery->orderBy('nom')->get(['id', 'nom']),
            'profilIdInitial' => $request->integer('profil_id') ?: null,
            'motifs' => $this->motifsChangementPoste(),
        ]);
    }

    public function storeChangementPoste(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profil_id' => 'required|exists:profiles,id',
            'date_effet' => 'required|date',
            'fonction' => 'nullable|string|max:255',
            'departement' => 'nullable|string|max:255',
            'site' => 'nullable|string|max:100',
            'n_plus_1_id' => 'nullable|exists:profiles,id',
            'motif' => 'nullable|string|max:255',
            'motif_libre' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $profil = Profil::query()->findOrFail($validated['profil_id']);

        if (! $user || ! $user->canAccessProfil($profil)) {
            abort(403, 'Vous n\'avez pas accès à ce profil.');
        }

        $motifChoisi = trim((string) ($validated['motif'] ?? ''));
        $motif = $motifChoisi === 'Autre'
            ? trim((string) ($validated['motif_libre'] ?? ''))
            : ($motifChoisi !== '' ? $motifChoisi : null);

        $this->mouvements->enregistrerChangementPoste(
            $profil,
            [
                'fonction' => $validated['fonction'] ?? null,
                'departement' => $validated['departement'] ?? null,
                'site' => $validated['site'] ?? null,
                'n_plus_1_id' => $validated['n_plus_1_id'] ?? null,
            ],
            \Illuminate\Support\Carbon::parse($validated['date_effet']),
            $motif,
            $user,
        );

        return redirect()
            ->route('profils.mouvements.index')
            ->with('success', "Changement de poste enregistré pour {$profil->prenom} {$profil->nom}.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function profilsActifsPourSelect(): array
    {
        $user = Auth::user();
        $query = Profil::query()->where('statut', 'actif');
        if ($user) {
            $user->applyProfilVisibilityScope($query);
        }

        return $query
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule', 'fonction', 'departement', 'site', 'n_plus_1_id'])
            ->map(fn (Profil $p) => [
                'id' => $p->id,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'matricule' => $p->matricule,
                'fonction' => $p->fonction,
                'departement' => $p->departement,
                'site' => $p->site,
                'n_plus_1_id' => $p->n_plus_1_id,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, nom: string, prenom: string, matricule: string}>
     */
    private function profilsPourHierarchie(): array
    {
        $user = Auth::user();
        $query = Profil::query()->where('statut', 'actif');
        if ($user) {
            $user->applyProfilVisibilityScope($query);
        }

        return $query
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule'])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function motifsDepart(): array
    {
        return [
            'Fin de contrat',
            'Démission',
            'Licenciement',
            'Retraite',
            'Mutation hors filiale',
            'Décès',
            'Autre',
        ];
    }

    /**
     * @return list<string>
     */
    private function motifsChangementPoste(): array
    {
        return [
            'Promotion',
            'Mutation interne',
            'Réorganisation',
            'Changement d’agence',
            'Autre',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toListRow(ProfilMouvement $m): array
    {
        return [
            'id' => $m->id,
            'type' => $m->type,
            'type_label' => $m->typeLabel(),
            'date_effet' => $m->date_effet?->format('Y-m-d'),
            'motif' => $m->motif,
            'fonction_avant' => $m->fonction_avant,
            'fonction_apres' => $m->fonction_apres,
            'departement_avant' => $m->departement_avant,
            'departement_apres' => $m->departement_apres,
            'site_avant' => $m->site_avant,
            'site_apres' => $m->site_apres,
            'profil' => $m->profil ? [
                'id' => $m->profil->id,
                'nom' => $m->profil->nom,
                'prenom' => $m->profil->prenom,
                'matricule' => $m->profil->matricule,
                'statut' => $m->profil->statut,
            ] : null,
            'n_plus_1_avant' => $m->nPlus1Avant ? $this->profilCourt($m->nPlus1Avant) : null,
            'n_plus_1_apres' => $m->nPlus1Apres ? $this->profilCourt($m->nPlus1Apres) : null,
            'createur' => $m->createur?->name,
            'created_at' => $m->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array{id: int, nom: string, prenom: string, matricule: string}
     */
    private function profilCourt(Profil $profil): array
    {
        return [
            'id' => $profil->id,
            'nom' => $profil->nom,
            'prenom' => $profil->prenom,
            'matricule' => $profil->matricule,
        ];
    }
}
