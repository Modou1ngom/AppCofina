<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Services\MissionNotificationService;
use Illuminate\Console\Command;

class SendMissionReminders extends Command
{
    protected $signature = 'missions:send-reminders';

    protected $description = 'Envoie les rappels e-mail pour les missions en attente (toutes les 12 h).';

    public function handle(): int
    {
        $seuil = now()->subHours(12);

        $missions = Mission::query()
            ->whereNotIn('current_step', [
                Mission::STEP_BROUILLON,
                Mission::STEP_CLOTUREE,
            ])
            ->where('status', '!=', 'rejete')
            ->where(function ($q) use ($seuil) {
                $q->whereNull('last_reminder_at')
                    ->orWhere('last_reminder_at', '<=', $seuil);
            })
            ->get();

        $count = 0;

        foreach ($missions as $mission) {
            MissionNotificationService::envoyerRappelsEtapeCourante($mission);
            $mission->update(['last_reminder_at' => now()]);
            $count++;
        }

        $this->info("Rappels envoyés pour {$count} mission(s).");

        return self::SUCCESS;
    }
}
