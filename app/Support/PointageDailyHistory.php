<?php

namespace App\Support;

use App\Models\Pointage;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PointageDailyHistory
{
    /**
     * Agrège les pointages par jour (même logique que GET /api/mobile/attendance/history).
     *
     * @return array{records: list<array<string, mixed>>, period_from: string, period_to: string}
     */
    public static function forUserId(int $userId, ?string $from, ?string $to): array
    {
        $tz = config('app.timezone');

        $toCarbon = $to !== null && $to !== ''
            ? Carbon::parse($to, $tz)->endOfDay()
            : now($tz)->endOfDay();
        $fromCarbon = $from !== null && $from !== ''
            ? Carbon::parse($from, $tz)->startOfDay()
            : (clone $toCarbon)->subDays(90)->startOfDay();

        $rows = Pointage::query()
            ->where('user_id', $userId)
            ->whereBetween('enregistre_at', [$fromCarbon, $toCarbon])
            ->orderBy('enregistre_at')
            ->get(['id', 'sens', 'enregistre_at']);

        $byDay = $rows->groupBy(fn (Pointage $p) => $p->enregistre_at->copy()->timezone($tz)->format('Y-m-d'));

        $records = $byDay->map(function (Collection $dayRows, string $dateKey) use ($tz): array {
            $entrees = $dayRows->filter(fn (Pointage $p) => $p->sens === Pointage::SENS_ENTREE)->sortBy('enregistre_at');
            $sorties = $dayRows->filter(fn (Pointage $p) => $p->sens === Pointage::SENS_SORTIE)->sortBy('enregistre_at');

            $firstIn = $entrees->first();
            $lastOut = $sorties->last();

            $checkIn = $firstIn ? $firstIn->enregistre_at->copy()->timezone($tz)->toIso8601String() : null;
            $checkOut = $lastOut ? $lastOut->enregistre_at->copy()->timezone($tz)->toIso8601String() : null;

            $status = 'open';
            if ($checkIn !== null && $checkOut !== null) {
                $status = 'complete';
            } elseif ($checkIn === null && $checkOut !== null) {
                $status = 'checkout_only';
            } elseif ($checkIn !== null) {
                $status = 'open';
            } else {
                $status = 'unknown';
            }

            return [
                'id' => $dayRows->first()->id,
                'date' => $dateKey,
                'check_in' => $checkIn,
                'checkIn' => $checkIn,
                'check_out' => $checkOut,
                'checkOut' => $checkOut,
                'status' => $status,
            ];
        })->values()->sortByDesc('date')->values()->all();

        return [
            'records' => $records,
            'period_from' => $fromCarbon->format('Y-m-d'),
            'period_to' => $toCarbon->format('Y-m-d'),
        ];
    }

    /**
     * Synthèse du jour courant pour le tableau de bord mobile (entrée / sortie / statut).
     *
     * @return array<string, mixed>
     */
    public static function todayForUserId(int $userId): array
    {
        $tz = config('app.timezone');
        $dateKey = now($tz)->format('Y-m-d');
        $todayStart = now($tz)->startOfDay();
        $todayEnd = now($tz)->endOfDay();

        $rows = Pointage::query()
            ->where('user_id', $userId)
            ->whereBetween('enregistre_at', [$todayStart, $todayEnd])
            ->orderBy('enregistre_at')
            ->get(['id', 'sens', 'enregistre_at']);

        $entrees = $rows->filter(fn (Pointage $p) => $p->sens === Pointage::SENS_ENTREE)->sortBy('enregistre_at');
        $sorties = $rows->filter(fn (Pointage $p) => $p->sens === Pointage::SENS_SORTIE)->sortBy('enregistre_at');

        $firstIn = $entrees->first();
        $lastOut = $sorties->last();

        $checkIn = $firstIn ? $firstIn->enregistre_at->copy()->timezone($tz)->toIso8601String() : null;
        $checkOut = $lastOut ? $lastOut->enregistre_at->copy()->timezone($tz)->toIso8601String() : null;

        if ($checkIn !== null && $checkOut !== null) {
            $status = 'complete';
        } elseif ($checkIn === null && $checkOut !== null) {
            $status = 'checkout_only';
        } elseif ($checkIn !== null) {
            $status = 'open';
        } else {
            $status = 'pending';
        }

        $punches = $rows->map(function (Pointage $p) use ($tz): array {
            $iso = $p->enregistre_at->copy()->timezone($tz)->toIso8601String();
            $type = $p->sens === Pointage::SENS_SORTIE ? 'checkout' : 'checkin';

            return [
                'id' => $p->id,
                'sens' => $p->sens,
                'type' => $type,
                'enregistre_at' => $iso,
                'recorded_at' => $iso,
                'recordedAt' => $iso,
            ];
        })->values()->all();

        $summary = [
            'id' => $rows->first()?->id,
            'date' => $dateKey,
            'check_in' => $checkIn,
            'checkIn' => $checkIn,
            'check_out' => $checkOut,
            'checkOut' => $checkOut,
            'status' => $status,
            'punches' => $punches,
            'items' => $punches,
            'records' => $punches,
        ];

        return $summary;
    }
}
