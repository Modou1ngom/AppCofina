<?php

namespace App\Notifications;

use App\Models\SigStaff;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SuiviSignatureEncoursSeuilDepasseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SigStaff $staff
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $taux = $this->staff->tauxEncoursFondsPropres();
        $seuil = config('sig.encours_taux_seuil_pct', 10);

        return [
            'title' => 'Suivi signature — seuil d’encours dépassé',
            'body' => sprintf(
                'Le taux encours / fonds propres (%.2f %% dépasse le seuil de %s %% — fiche %s %s (réf. %s).',
                $taux ?? 0.0,
                $seuil,
                $this->staff->prenom,
                $this->staff->nom,
                $this->staff->reference
            ),
            'sig_staff_id' => $this->staff->id,
            'taux_pct' => $taux,
            'seuil_pct' => $seuil,
        ];
    }
}
