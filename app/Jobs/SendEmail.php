<?php

namespace App\Jobs;

use App\Mail\EmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  string  $subject  Sujet de l'email
     * @param  string|array  $to  Destinataire(s)
     * @param  array  $cc  Copie
     * @param  array  $bcc  Copie cachée
     * @param  string  $view  Chemin de la vue Blade (ex: emails.habilitations.confirmation-creation)
     * @param  array  $data  Données pour la vue
     */
    public function __construct(
        public string $subject,
        public string|array $to,
        public array $cc,
        public array $bcc,
        public string $view,
        public array $data,
    ) {}

    public function handle(): void
    {
        $to = is_array($this->to) ? $this->to : [$this->to];

        try {
            Mail::to($this->to)
                ->cc($this->cc)
                ->bcc($this->bcc)
                ->send(new EmailSender($this->subject, $this->view, $this->data));

            Log::channel('single')->info('Email envoyé', [
                'to' => $to,
                'subject' => $this->subject,
                'view' => $this->view,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Échec envoi email', [
                'to' => $to,
                'subject' => $this->subject,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
