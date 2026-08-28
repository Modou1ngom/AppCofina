<?php

namespace App\Http\Controllers;

use App\Helpers\FilialeHelper;
use App\Models\EnqueteSatisfactionReponse;
use App\Models\Filiale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EnqueteSatisfactionController extends Controller
{
    public function create(Request $request): Response
    {
        $filiales = $this->filialesActives();
        $filialeId = $request->integer('filiale_id') ?: null;
        if ($filialeId && ! $filiales->contains('id', $filialeId)) {
            $filialeId = null;
        }

        return Inertia::render('enquete-satisfaction/Formulaire', [
            'criteres' => EnqueteSatisfactionReponse::CRITERES,
            'recommandations' => EnqueteSatisfactionReponse::RECOMMANDATIONS,
            'qualitesPriseEnCharge' => EnqueteSatisfactionReponse::QUALITE_PRISE_EN_CHARGE,
            'delaisReponse' => EnqueteSatisfactionReponse::DELAIS_REPONSE,
            'filiales' => $filiales,
            'filialeIdInitiale' => $filialeId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $noteRule = ['required', 'integer', 'between:1,5'];

        $validated = $request->validate([
            'filiale_id' => [
                'required',
                'integer',
                Rule::exists('filiales', 'id')->where('actif', true),
            ],
            'nom' => ['nullable', 'string', 'max:120'],
            'matricule' => ['nullable', 'string', 'max:64'],
            'service' => ['nullable', 'string', 'max:120'],
            'qualite_accueil_ecoute' => $noteRule,
            'rapidite_prise_en_charge' => $noteRule,
            'temps_resolution' => $noteRule,
            'professionnalisme_equipe_it' => $noteRule,
            'qualite_solution' => $noteRule,
            'communication_suivi' => $noteRule,
            'satisfaction_globale' => $noteRule,
            'remarques_difficultes' => ['nullable', 'string', 'max:5000'],
            'suggestions_amelioration' => ['nullable', 'string', 'max:5000'],
            'besoins_attentes' => ['nullable', 'string', 'max:5000'],
            'recommandation' => ['required', Rule::in(array_keys(EnqueteSatisfactionReponse::RECOMMANDATIONS))],
            'qualite_prise_en_charge' => ['required', Rule::in(array_keys(EnqueteSatisfactionReponse::QUALITE_PRISE_EN_CHARGE))],
            'delai_reponse' => ['required', Rule::in(array_keys(EnqueteSatisfactionReponse::DELAIS_REPONSE))],
            'commentaires_additionnels' => ['nullable', 'string', 'max:5000'],
        ]);

        EnqueteSatisfactionReponse::query()->create([
            ...$validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('enquete-satisfaction.merci');
    }

    public function merci(): Response
    {
        return Inertia::render('enquete-satisfaction/Merci');
    }

    public function index(): Response
    {
        $reponses = EnqueteSatisfactionReponse::query()
            ->with('filiale:id,nom')
            ->latest()
            ->paginate(20)
            ->through(fn (EnqueteSatisfactionReponse $r) => [
                'id' => $r->id,
                'filiale' => $r->filiale?->nom,
                'nom' => $r->nom,
                'matricule' => $r->matricule,
                'service' => $r->service,
                'satisfaction_globale' => $r->satisfaction_globale,
                'moyenne_notes' => $r->moyenneNotes(),
                'recommandation' => $r->recommandation,
                'recommandation_label' => EnqueteSatisfactionReponse::RECOMMANDATIONS[$r->recommandation] ?? $r->recommandation,
                'created_at' => $r->created_at?->format('d/m/Y H:i'),
            ]);

        $lienPublic = route('enquete-satisfaction.create', array_filter([
            'filiale_id' => FilialeHelper::getCurrentFilialeId(),
        ]));

        return Inertia::render('enquete-satisfaction/Index', [
            'reponses' => $reponses,
            'lienPublic' => $lienPublic,
            'stats' => [
                'total' => EnqueteSatisfactionReponse::query()->count(),
                'moyenne_globale' => round((float) EnqueteSatisfactionReponse::query()->avg('satisfaction_globale'), 2),
            ],
        ]);
    }

    public function show(EnqueteSatisfactionReponse $enqueteSatisfaction): Response
    {
        $r = $enqueteSatisfaction->loadMissing('filiale:id,nom');

        return Inertia::render('enquete-satisfaction/Show', [
            'reponse' => [
                'id' => $r->id,
                'filiale' => $r->filiale?->nom,
                'nom' => $r->nom,
                'matricule' => $r->matricule,
                'service' => $r->service,
                'notes' => collect(EnqueteSatisfactionReponse::CRITERES)->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'valeur' => $r->{$key},
                ])->values(),
                'moyenne_notes' => $r->moyenneNotes(),
                'remarques_difficultes' => $r->remarques_difficultes,
                'suggestions_amelioration' => $r->suggestions_amelioration,
                'besoins_attentes' => $r->besoins_attentes,
                'recommandation' => EnqueteSatisfactionReponse::RECOMMANDATIONS[$r->recommandation] ?? $r->recommandation,
                'qualite_prise_en_charge' => EnqueteSatisfactionReponse::QUALITE_PRISE_EN_CHARGE[$r->qualite_prise_en_charge] ?? $r->qualite_prise_en_charge,
                'delai_reponse' => EnqueteSatisfactionReponse::DELAIS_REPONSE[$r->delai_reponse] ?? $r->delai_reponse,
                'commentaires_additionnels' => $r->commentaires_additionnels,
                'created_at' => $r->created_at?->format('d/m/Y à H:i'),
            ],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Filiale>
     */
    private function filialesActives()
    {
        return Filiale::query()
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom']);
    }
}
