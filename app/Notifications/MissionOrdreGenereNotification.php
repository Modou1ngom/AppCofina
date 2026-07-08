<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MissionOrdreGenereNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $missionId,
        public string $objet,
        public string $message,
        public ?string $pdfUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'mission_id' => $this->missionId,
            'objet' => $this->objet,
            'message' => $this->message,
            'pdf_url' => $this->pdfUrl,
            'type' => 'mission_ordre_genere',
        ];
    }
}
