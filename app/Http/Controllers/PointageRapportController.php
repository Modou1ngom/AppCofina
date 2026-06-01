<?php

namespace App\Http\Controllers;

use App\Models\Pointage;
use App\Models\PointageDeclaration;
use App\Models\PointageSite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PointageRapportController extends Controller
{
    /**
     * Tableau de bord administration / RH — vue synthétique du module pointage.
     */
    public function index(): Response
    {
        $todayStart = now()->startOfDay();
        $weekStart = now()->subDays(7)->startOfDay();

        $pointagesAujourdhui = Pointage::query()->where('enregistre_at', '>=', $todayStart)->count();
        $collaborateursSemaine = Pointage::query()
            ->where('enregistre_at', '>=', $weekStart)
            ->distinct()
            ->count('user_id');

        $stats = [
            'pointages_aujourdhui' => $pointagesAujourdhui,
            'sites_actifs' => PointageSite::query()->where('actif', true)->count(),
            'declarations_pending_manager' => PointageDeclaration::query()
                ->where('statut', PointageDeclaration::STATUT_PENDING_MANAGER)
                ->count(),
            'declarations_pending_rh' => PointageDeclaration::query()
                ->where('statut', PointageDeclaration::STATUT_PENDING_RH)
                ->count(),
            'collaborateurs_pointes_7j' => $collaborateursSemaine,
        ];

        $recentPointages = Pointage::query()
            ->with(['user', 'site'])
            ->orderByDesc('enregistre_at')
            ->limit(15)
            ->get()
            ->map(fn (Pointage $p) => [
                'id' => $p->id,
                'sens' => $p->sens,
                'type' => $p->sens === Pointage::SENS_SORTIE ? 'checkout' : 'checkin',
                'enregistre_at' => $p->enregistre_at->toIso8601String(),
                'source' => $p->source,
                'user_name' => $p->user?->name,
                'user_email' => $p->user?->email,
                'site_nom' => $p->site?->nom,
            ]);

        return Inertia::render('pointage/Rapport', [
            'stats' => $stats,
            'recentPointages' => $recentPointages,
        ]);
    }

    public function exportQuotidien(Request $request): StreamedResponse
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        $rows = Pointage::query()
            ->with(['user', 'site'])
            ->whereDate('enregistre_at', $date)
            ->orderBy('enregistre_at')
            ->get();

        $filename = 'pointages-quotidien-'.$date.'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Date/heure', 'Collaborateur', 'E-mail', 'Site', 'Sens', 'Source']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->enregistre_at->format('Y-m-d H:i:s'),
                    $p->user?->name,
                    $p->user?->email,
                    $p->site?->nom,
                    $p->sens,
                    $p->source,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportJournalierRh(Request $request): StreamedResponse
    {
        $from = $request->get('from', now()->subDays(30)->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));

        $rows = Pointage::query()
            ->with(['user', 'site'])
            ->whereDate('enregistre_at', '>=', $from)
            ->whereDate('enregistre_at', '<=', $to)
            ->orderBy('enregistre_at')
            ->get();

        $filename = 'pointages-journalier-'.$from.'_au_'.$to.'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Date/heure', 'Collaborateur', 'E-mail', 'Site', 'Sens', 'Source']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->enregistre_at->format('Y-m-d H:i:s'),
                    $p->user?->name,
                    $p->user?->email,
                    $p->site?->nom,
                    $p->sens,
                    $p->source,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportSyntheseRh(Request $request): StreamedResponse
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));

        $pointages = Pointage::query()
            ->whereDate('enregistre_at', '>=', $from)
            ->whereDate('enregistre_at', '<=', $to)
            ->get(['user_id', 'enregistre_at']);

        $aggregates = $pointages
            ->groupBy(fn (Pointage $p) => $p->user_id.'|'.$p->enregistre_at->format('Y-m-d'))
            ->map(fn ($group, $key) => [
                'user_id' => (int) explode('|', $key)[0],
                'jour' => explode('|', $key)[1],
                'total' => $group->count(),
            ])
            ->sortBy('jour')
            ->values();

        $userIds = $aggregates->pluck('user_id')->unique()->all();
        $users = \App\Models\User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $filename = 'pointages-synthese-'.$from.'_au_'.$to.'.csv';

        return response()->streamDownload(function () use ($aggregates, $users): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Jour', 'Collaborateur', 'E-mail', 'Nombre de pointages']);
            foreach ($aggregates as $row) {
                $u = $users->get($row['user_id']);
                fputcsv($out, [
                    $row['jour'],
                    $u?->name,
                    $u?->email,
                    $row['total'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
