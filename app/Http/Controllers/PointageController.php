<?php

namespace App\Http\Controllers;

use App\Models\Pointage;
use App\Models\PointageSite;
use App\Support\PointageDailyHistory;
use App\Support\PointageQrPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PointageController extends Controller
{
    /**
     * Pointage depuis le navigateur (téléphone ou bureau) : session web, pas d’API Bearer.
     * Données exposées alignées sur les réponses API (recorded_at, type checkin/checkout, meta).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $todayStart = now()->startOfDay();

        $pointagesToday = Pointage::query()
            ->where('user_id', $user->id)
            ->where('enregistre_at', '>=', $todayStart)
            ->with('site')
            ->orderByDesc('enregistre_at')
            ->get()
            ->map(fn (Pointage $p) => $this->serializePointageRow($p));

        $sites = PointageSite::query()
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom', 'code_public'])
            ->map(fn (PointageSite $s) => [
                'id' => $s->id,
                'nom' => $s->nom,
                'code_public' => $s->code_public,
            ]);

        return Inertia::render('pointage/Index', [
            'pointagesToday' => $pointagesToday,
            'sites' => $sites,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code_public' => ['nullable', 'string', 'max:64'],
            'qr_payload' => ['nullable', 'string', 'max:8192'],
            'sens' => ['required', 'in:entree,sortie'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'biometric_nonce' => ['nullable', 'string', 'max:512'],
        ]);

        $codePublic = PointageQrPayload::resolve(
            $validated['qr_payload'] ?? null,
            $validated['code_public'] ?? null
        );

        if ($codePublic === null || $codePublic === '') {
            return back()->with('error', 'Indiquez le code du site ou un QR valide.');
        }

        $site = PointageSite::query()
            ->where('code_public', $codePublic)
            ->where('actif', true)
            ->first();

        if ($site === null) {
            return back()->with('error', 'Site inconnu ou inactif. Vérifiez le code affiché sur le lieu de pointage.');
        }

        $meta = array_filter([
            'latitude' => array_key_exists('latitude', $validated) && $validated['latitude'] !== null
                ? (float) $validated['latitude']
                : null,
            'longitude' => array_key_exists('longitude', $validated) && $validated['longitude'] !== null
                ? (float) $validated['longitude']
                : null,
            'biometric_nonce' => $validated['biometric_nonce'] ?? null,
            'channel' => 'navigateur',
        ], static fn ($v) => $v !== null && $v !== '');

        Pointage::create([
            'user_id' => $request->user()->id,
            'pointage_site_id' => $site->id,
            'sens' => $validated['sens'],
            'enregistre_at' => now(),
            'source' => 'navigateur',
            'meta' => $meta !== [] ? $meta : null,
        ]);

        return back()->with('success', 'Pointage enregistré.');
    }

    /**
     * Historique agrégé par jour (aligné sur GET /api/mobile/attendance/history).
     */
    public function historique(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $result = PointageDailyHistory::forUserId(
            $request->user()->id,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        return Inertia::render('pointage/Historique', [
            'records' => $result['records'],
            'filters' => [
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
            'period' => [
                'from' => $result['period_from'],
                'to' => $result['period_to'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePointageRow(Pointage $p): array
    {
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
    }
}
