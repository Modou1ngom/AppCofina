<?php

namespace App\Http\Controllers;

use App\Helpers\FilialeHelper;
use App\Models\EnqueteSatisfactionReponse;
use App\Models\EnqueteSatisfactionSuivi;
use App\Models\Filiale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $reponses = EnqueteSatisfactionReponse::query()
            ->with('filiale:id,nom')
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
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

    public function rapport(Request $request): Response
    {
        $periode = $request->string('periode')->toString();
        if (! in_array($periode, ['tout', 'mois', 'trimestre', 'personnalisee'], true)) {
            $periode = 'tout';
        }

        [$du, $au] = $this->bornesPeriode($request, $periode);

        $reponses = EnqueteSatisfactionReponse::query()
            ->with('suivis')
            ->when($du, fn ($q) => $q->whereDate('created_at', '>=', $du->toDateString()))
            ->when($au, fn ($q) => $q->whereDate('created_at', '<=', $au->toDateString()))
            ->latest()
            ->get();

        $total = $reponses->count();
        $criteres = $this->syntheseCriteres($reponses);
        $noteMoyenne = $total > 0
            ? round((float) collect($criteres)->avg('note'), 1)
            : null;
        $moyenneGlobale = $total > 0
            ? round((float) $reponses->avg('satisfaction_globale'), 1)
            : null;
        $recommandationsOui = $reponses->where('recommandation', 'oui')->count();

        $filialeId = FilialeHelper::getCurrentFilialeId();
        $entite = $filialeId
            ? (string) (Filiale::query()->whereKey($filialeId)->value('nom') ?: 'Filiale')
            : 'Toutes les filiales';

        $debutEffectif = $reponses->min('created_at');
        $finEffective = $reponses->max('created_at');

        return Inertia::render('enquete-satisfaction/Rapport', [
            'entite' => $entite,
            'service' => 'Support & Exploitation IT',
            'responsable' => 'IT Manager / Responsable Support IT',
            'periode' => $periode,
            'du' => $du?->toDateString(),
            'au' => $au?->toDateString(),
            'dateDebut' => ($du ?? ($debutEffectif ? Carbon::parse($debutEffectif) : null))?->format('d/m/Y'),
            'dateFin' => ($au ?? ($finEffective ? Carbon::parse($finEffective) : null))?->format('d/m/Y'),
            'indicateurs' => [
                'taux_satisfaction' => $moyenneGlobale !== null ? (int) round($moyenneGlobale / 5 * 100) : null,
                'nombre_repondants' => $total,
                'note_moyenne' => $noteMoyenne,
                'taux_recommandation' => $total > 0 ? (int) round($recommandationsOui / $total * 100) : null,
            ],
            'criteres' => $criteres,
            'analyse' => $this->analyseQualitative($criteres),
            'suivis' => [
                'remarque' => $this->lignesSuivi($reponses, 'remarque'),
                'suggestion' => $this->lignesSuivi($reponses, 'suggestion'),
                'besoin' => $this->lignesSuivi($reponses, 'besoin'),
            ],
            'decisions' => EnqueteSatisfactionSuivi::DECISIONS,
            'statuts' => EnqueteSatisfactionSuivi::STATUTS,
            'libellesSuivi' => EnqueteSatisfactionSuivi::TYPES,
        ]);
    }

    public function updateSuivi(Request $request, EnqueteSatisfactionReponse $enqueteSatisfaction): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(EnqueteSatisfactionReponse::CHAMPS_SUIVI))],
            'decision' => ['required', Rule::in(array_keys(EnqueteSatisfactionSuivi::DECISIONS))],
            'statut' => ['required', Rule::in(array_keys(EnqueteSatisfactionSuivi::STATUTS))],
            'element_reponse' => ['nullable', 'string', 'max:5000'],
            'action' => ['nullable', 'string', 'max:5000'],
            'justification' => ['nullable', 'required_if:decision,rejeter', 'string', 'max:5000'],
            'date_cloture' => ['nullable', 'date'],
        ], [
            'decision.required' => 'Choisissez une décision.',
            'justification.required_if' => 'Une justification est requise pour rejeter la remarque.',
        ]);

        $champ = EnqueteSatisfactionReponse::CHAMPS_SUIVI[$validated['type']];
        if (! filled(trim((string) $enqueteSatisfaction->{$champ}))) {
            return back()->with('error', 'Cette réponse ne contient pas de texte à suivre pour cette rubrique.');
        }

        $enqueteSatisfaction->suivis()->updateOrCreate(
            ['type' => $validated['type']],
            [
                'decision' => $validated['decision'],
                'statut' => $validated['statut'],
                'element_reponse' => ($validated['element_reponse'] ?? null) ?: null,
                'action' => ($validated['action'] ?? null) ?: null,
                'justification' => $validated['decision'] === 'rejeter' ? (($validated['justification'] ?? null) ?: null) : null,
                'date_cloture' => ($validated['date_cloture'] ?? null) ?: null,
            ],
        );

        return back()->with('success', 'Suivi enregistré.');
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function bornesPeriode(Request $request, string $periode): array
    {
        if ($periode === 'mois') {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        if ($periode === 'trimestre') {
            return [now()->startOfQuarter(), now()->endOfQuarter()];
        }

        if ($periode === 'personnalisee') {
            $du = $request->date('du')?->startOfDay();
            $au = $request->date('au')?->endOfDay();

            return [$du, $au];
        }

        return [null, null];
    }

    /**
     * @param  Collection<int, EnqueteSatisfactionReponse>  $reponses
     * @return list<array{key: string, label: string, court: string, note: ?float, satisfaction: ?int}>
     */
    private function syntheseCriteres(Collection $reponses): array
    {
        $total = $reponses->count();

        return collect(EnqueteSatisfactionReponse::CRITERES)
            ->map(function (string $label, string $key) use ($reponses, $total) {
                $note = $total > 0 ? round((float) $reponses->avg($key), 1) : null;

                return [
                    'key' => $key,
                    'label' => $label,
                    'court' => EnqueteSatisfactionReponse::CRITERES_COURT[$key] ?? $label,
                    'note' => $note,
                    'satisfaction' => $note !== null ? (int) round($note / 5 * 100) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{key: string, label: string, note: ?float}>  $criteres
     * @return array{positifs: list<string>, axes: list<string>}
     */
    private function analyseQualitative(array $criteres): array
    {
        $parCle = collect($criteres)->keyBy('key');
        $note = fn (string $key): ?float => $parCle->get($key)['note'] ?? null;
        $elevee = fn (string $key): bool => ($note($key) ?? 0) >= 4;
        $faible = fn (string $key): bool => $note($key) !== null && $note($key) < 4;

        if ($parCle->every(fn (array $critere) => $critere['note'] === null)) {
            return ['positifs' => [], 'axes' => []];
        }

        $positifs = [];
        if ($elevee('professionnalisme_equipe_it')) {
            $positifs[] = 'Le professionnalisme de l’équipe IT obtient une note élevée.';
        }
        if ($elevee('qualite_accueil_ecoute')) {
            $positifs[] = 'La qualité de l’accueil et de l’écoute est globalement appréciée.';
        }
        if ($elevee('qualite_solution')) {
            $positifs[] = 'Les utilisateurs reconnaissent la qualité des solutions apportées.';
        }
        if ($elevee('rapidite_prise_en_charge') && $elevee('temps_resolution')) {
            $positifs[] = 'Les délais de prise en charge et de résolution sont jugés satisfaisants.';
        }
        if ($elevee('communication_suivi')) {
            $positifs[] = 'La communication et le suivi des demandes sont appréciés.';
        }

        $axes = [];
        if ($faible('rapidite_prise_en_charge') || $faible('temps_resolution')) {
            $axes[] = 'Réduire les délais de prise en charge et de résolution.';
        }
        if ($faible('communication_suivi')) {
            $axes[] = 'Renforcer la communication avec les utilisateurs pendant le traitement des incidents.';
            $axes[] = 'Améliorer le suivi des demandes jusqu’à leur clôture.';
        }
        if ($faible('qualite_accueil_ecoute')) {
            $axes[] = 'Renforcer la qualité de l’accueil et de l’écoute.';
        }
        if ($faible('professionnalisme_equipe_it')) {
            $axes[] = 'Renforcer le professionnalisme perçu de l’équipe IT.';
        }
        if ($faible('qualite_solution')) {
            $axes[] = 'Améliorer la qualité des solutions apportées.';
        }

        return ['positifs' => $positifs, 'axes' => $axes];
    }

    /**
     * @param  Collection<int, EnqueteSatisfactionReponse>  $reponses
     * @return list<array<string, mixed>>
     */
    private function lignesSuivi(Collection $reponses, string $type): array
    {
        $champ = EnqueteSatisfactionReponse::CHAMPS_SUIVI[$type];

        return $reponses
            ->filter(fn (EnqueteSatisfactionReponse $reponse) => filled(trim((string) $reponse->{$champ})))
            ->map(function (EnqueteSatisfactionReponse $reponse) use ($type, $champ) {
                $suivi = $reponse->suivis->firstWhere('type', $type);

                return [
                    'reponse_id' => $reponse->id,
                    'type' => $type,
                    'texte' => $reponse->{$champ},
                    'auteur' => $reponse->nom,
                    'service' => $reponse->service,
                    'date' => $reponse->created_at?->format('d/m/Y'),
                    'decision' => $suivi?->decision,
                    'statut' => $suivi?->statut ?? 'a_analyser',
                    'element_reponse' => $suivi?->element_reponse,
                    'action' => $suivi?->action,
                    'justification' => $suivi?->justification,
                    'date_cloture' => $suivi?->date_cloture?->format('Y-m-d'),
                ];
            })
            ->values()
            ->all();
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
