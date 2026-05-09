<?php

namespace App\Http\Controllers;

use App\Models\SigStaff;
use App\Models\SigStaffEncoursConformiteEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SigEncoursConformiteController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,conformite');
    }

    public function rapport(Request $request): Response
    {
        $events = $this->filteredEventsQuery($request)
            ->orderByDesc('created_at')
            ->with(['staff:id,reference,prenom,nom', 'user:id,name,email'])
            ->paginate(50)
            ->withQueryString();

        $fichesEnDepassement = SigStaff::query()
            ->where('encours_conformite_en_depassement', true)
            ->orderBy('reference')
            ->get(['id', 'reference', 'prenom', 'nom', 'fonds_propres', 'encours_credit_individuel']);

        $staffPourFiltre = SigStaff::query()
            ->orderBy('reference')
            ->get(['id', 'reference', 'prenom', 'nom', 'numero_client_si']);

        return Inertia::render('suivi-signature/conformite/RapportEncours', [
            'events' => $events,
            'fichesEnDepassement' => $fichesEnDepassement,
            'staffPourFiltre' => $staffPourFiltre,
            'staffFilterHasHistory' => $this->staffFilterHasHistory($request),
            'filters' => [
                'du' => (string) $request->get('du', ''),
                'au' => (string) $request->get('au', ''),
                'type' => (string) $request->get('type', ''),
                'staff_id' => $request->filled('staff_id') ? (int) $request->get('staff_id') : null,
            ],
            'seuilTauxPct' => (float) config('sig.encours_taux_seuil_pct', 10),
            'typeOptions' => [
                ['value' => '', 'label' => 'Tous les types'],
                ['value' => SigStaffEncoursConformiteEvent::TYPE_DEPASSEMENT, 'label' => SigStaffEncoursConformiteEvent::typeLabel(SigStaffEncoursConformiteEvent::TYPE_DEPASSEMENT)],
                ['value' => SigStaffEncoursConformiteEvent::TYPE_RETOUR_CONFORME, 'label' => SigStaffEncoursConformiteEvent::typeLabel(SigStaffEncoursConformiteEvent::TYPE_RETOUR_CONFORME)],
                ['value' => SigStaffEncoursConformiteEvent::TYPE_COMMENTAIRE, 'label' => SigStaffEncoursConformiteEvent::typeLabel(SigStaffEncoursConformiteEvent::TYPE_COMMENTAIRE)],
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = 'rapport-conformite-encours-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Date',
                'Type',
                'Réf. staff',
                'Nom staff',
                'Fonds propres',
                'Encours consolidé',
                'Taux %',
                'Seuil %',
                'Commentaire',
                'Utilisateur',
            ], ';');

            $this->filteredEventsQuery($request)
                ->with(['staff:id,reference,prenom,nom', 'user:id,name,email'])
                ->orderByDesc('created_at')
                ->chunk(500, function ($chunk) use ($out): void {
                    foreach ($chunk as $e) {
                        /** @var SigStaffEncoursConformiteEvent $e */
                        fputcsv($out, [
                            $e->created_at?->format('Y-m-d H:i:s'),
                            SigStaffEncoursConformiteEvent::typeLabel($e->type),
                            $e->staff?->reference,
                            trim(($e->staff?->prenom ?? '').' '.($e->staff?->nom ?? '')),
                            $e->fonds_propres !== null ? (string) $e->fonds_propres : '',
                            (string) $e->encours_consolide,
                            $e->taux_pct !== null ? (string) $e->taux_pct : '',
                            (string) $e->seuil_pct,
                            $e->commentaire !== null ? str_replace(["\r", "\n"], ' ', $e->commentaire) : '',
                            $e->user?->name ?? $e->user?->email ?? '',
                        ], ';');
                    }
                });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storeCommentaire(Request $request, SigStaff $staff): RedirectResponse
    {
        $validated = $request->validate([
            'commentaire' => 'required|string|min:3|max:10000',
        ]);

        $staff->synchroniserEncoursTotaux();
        $staff->refresh();

        $seuil = (float) config('sig.encours_taux_seuil_pct', 10);
        $fp = $staff->fonds_propres !== null ? (float) $staff->fonds_propres : null;

        SigStaffEncoursConformiteEvent::query()->create([
            'sig_staff_id' => $staff->id,
            'user_id' => $request->user()->id,
            'type' => SigStaffEncoursConformiteEvent::TYPE_COMMENTAIRE,
            'fonds_propres' => $fp,
            'encours_consolide' => $staff->encoursTotal(),
            'taux_pct' => $staff->tauxEncoursFondsPropres(),
            'seuil_pct' => $seuil,
            'commentaire' => $validated['commentaire'],
        ]);

        return back()->with('success', 'Commentaire enregistré dans l’historique de conformité.');
    }

    /**
     * @return Builder<SigStaffEncoursConformiteEvent>
     */
    private function filteredEventsQuery(Request $request): Builder
    {
        $query = SigStaffEncoursConformiteEvent::query();

        if ($request->filled('du')) {
            $query->where('created_at', '>=', Carbon::parse($request->get('du'))->startOfDay());
        }
        if ($request->filled('au')) {
            $query->where('created_at', '<=', Carbon::parse($request->get('au'))->endOfDay());
        }

        $type = (string) $request->get('type', '');
        if ($type !== '' && in_array($type, [
            SigStaffEncoursConformiteEvent::TYPE_DEPASSEMENT,
            SigStaffEncoursConformiteEvent::TYPE_RETOUR_CONFORME,
            SigStaffEncoursConformiteEvent::TYPE_COMMENTAIRE,
        ], true)) {
            $query->where('type', $type);
        }

        if ($request->filled('staff_id')) {
            $query->where('sig_staff_id', (int) $request->get('staff_id'));
        }

        return $query;
    }

    /**
     * Indique si la fiche choisie a au moins un événement (toutes dates), pour guider l’utilisateur quand le filtre date exclut tout.
     */
    private function staffFilterHasHistory(Request $request): ?bool
    {
        if (! $request->filled('staff_id')) {
            return null;
        }

        $id = (int) $request->get('staff_id');

        return SigStaffEncoursConformiteEvent::query()
            ->where('sig_staff_id', $id)
            ->exists();
    }
}
