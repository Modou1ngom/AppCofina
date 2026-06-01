<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Pointage;
use App\Models\PointageSite;
use App\Models\User;
use App\Services\PointageNotificationService;
use App\Support\PointageDailyHistory;
use App\Support\PointageQrPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAttendanceController extends Controller
{
    public function checkin(Request $request): JsonResponse
    {
        return $this->recordAttendance($request, Pointage::SENS_ENTREE, 'checkin');
    }

    public function checkout(Request $request): JsonResponse
    {
        return $this->recordAttendance($request, Pointage::SENS_SORTIE, 'checkout');
    }

    private function recordAttendance(Request $request, string $sens, string $responseType): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => ['required', 'string', 'max:8192'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'biometric_nonce' => ['required', 'string', 'max:512'],
            'type' => ['nullable', 'string', 'max:32'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $codePublic = PointageQrPayload::parse($validated['qr_payload']);
        if ($codePublic === null || $codePublic === '') {
            return response()->json(['message' => 'QR ou code site invalide.'], 422);
        }

        $site = PointageSite::query()
            ->where('code_public', $codePublic)
            ->where('actif', true)
            ->first();

        if ($site === null) {
            return response()->json(['message' => 'Site inconnu ou inactif.'], 422);
        }

        $meta = [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'biometric_nonce' => $validated['biometric_nonce'],
            'qr_payload' => mb_substr($validated['qr_payload'], 0, 2000),
        ];

        $pointage = Pointage::create([
            'user_id' => $user->id,
            'pointage_site_id' => $site->id,
            'sens' => $sens,
            'enregistre_at' => now(),
            'source' => 'mobile',
            'meta' => $meta,
        ]);

        $iso = $pointage->enregistre_at->toIso8601String();

        PointageNotificationService::notifyAttendanceRecorded(
            $user,
            $pointage,
            $sens === Pointage::SENS_SORTIE ? 'Sortie' : 'Entrée'
        );

        return response()->json([
            'id' => $pointage->id,
            'recorded_at' => $iso,
            'recordedAt' => $iso,
            'type' => $responseType,
        ], 200);
    }

    /**
     * Pointage du jour (tableau de bord) : entrée / sortie et liste des passages.
     */
    public function today(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $summary = PointageDailyHistory::todayForUserId($user->id);

        return response()->json([
            'data' => $summary,
            ...$summary,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $result = PointageDailyHistory::forUserId(
            $user->id,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );
        $records = $result['records'];

        return response()->json(['data' => $records, 'records' => $records, 'items' => $records]);
    }
}
