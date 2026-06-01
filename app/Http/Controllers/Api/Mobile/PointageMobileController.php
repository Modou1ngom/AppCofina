<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Pointage;
use App\Models\PointageSite;
use App\Services\PointageNotificationService;
use App\Support\PointageDailyHistory;
use App\Support\PointageQrPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointageMobileController extends Controller
{
    /**
     * Sites actifs (nom + code public pour scan / saisie dans l’app).
     */
    public function sites(): JsonResponse
    {
        $sites = PointageSite::query()
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom', 'code_public'])
            ->map(fn (PointageSite $s) => [
                'id' => $s->id,
                'nom' => $s->nom,
                'code_public' => $s->code_public,
            ]);

        return response()->json(['data' => $sites]);
    }

    /**
     * Enregistrer un pointage depuis l’application mobile (code_public et/ou qr_payload, comme l’API attendance).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code_public' => ['nullable', 'string', 'max:64'],
            'qr_payload' => ['nullable', 'string', 'max:8192'],
            'sens' => ['required', 'in:entree,sortie'],
            'device_id' => ['nullable', 'string', 'max:128'],
        ]);

        $codePublic = PointageQrPayload::resolve(
            $validated['qr_payload'] ?? null,
            $validated['code_public'] ?? null
        );

        if ($codePublic === null || $codePublic === '') {
            return response()->json([
                'message' => 'Fournissez code_public ou qr_payload.',
            ], 422);
        }

        $site = PointageSite::query()
            ->where('code_public', $codePublic)
            ->where('actif', true)
            ->first();

        if ($site === null) {
            return response()->json([
                'message' => 'Site inconnu ou inactif.',
            ], 422);
        }

        $meta = [];
        if (! empty($validated['device_id'])) {
            $meta['device_id'] = $validated['device_id'];
        }

        $pointage = Pointage::create([
            'user_id' => $request->user()->id,
            'pointage_site_id' => $site->id,
            'sens' => $validated['sens'],
            'enregistre_at' => now(),
            'source' => 'mobile',
            'meta' => $meta !== [] ? $meta : null,
        ]);

        $iso = $pointage->enregistre_at->toIso8601String();
        $type = $pointage->sens === Pointage::SENS_SORTIE ? 'checkout' : 'checkin';

        PointageNotificationService::notifyAttendanceRecorded(
            $request->user(),
            $pointage,
            $validated['sens'] === Pointage::SENS_SORTIE ? 'Sortie' : 'Entrée'
        );

        return response()->json([
            'message' => 'Pointage enregistré.',
            'data' => [
                'id' => $pointage->id,
                'sens' => $pointage->sens,
                'type' => $type,
                'enregistre_at' => $iso,
                'recorded_at' => $iso,
                'recordedAt' => $iso,
                'site' => [
                    'id' => $site->id,
                    'nom' => $site->nom,
                ],
            ],
        ], 201);
    }

    /**
     * Pointages du collaborateur pour la journée en cours (fuseau serveur).
     */
    public function today(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $summary = PointageDailyHistory::todayForUserId($userId);

        $todayStart = now()->startOfDay();

        $rows = Pointage::query()
            ->where('user_id', $userId)
            ->where('enregistre_at', '>=', $todayStart)
            ->with('site')
            ->orderByDesc('enregistre_at')
            ->get()
            ->map(function (Pointage $p): array {
                $iso = $p->enregistre_at->toIso8601String();
                $type = $p->sens === Pointage::SENS_SORTIE ? 'checkout' : 'checkin';

                return [
                    'id' => $p->id,
                    'sens' => $p->sens,
                    'type' => $type,
                    'source' => $p->source,
                    'enregistre_at' => $iso,
                    'recorded_at' => $iso,
                    'recordedAt' => $iso,
                    'site' => $p->site ? ['id' => $p->site->id, 'nom' => $p->site->nom] : null,
                    'meta' => $p->meta,
                ];
            });

        $summary['punches'] = $rows->values()->all();
        $summary['items'] = $summary['punches'];
        $summary['records'] = $summary['punches'];

        return response()->json([
            'data' => $summary,
            ...$summary,
        ]);
    }
}
