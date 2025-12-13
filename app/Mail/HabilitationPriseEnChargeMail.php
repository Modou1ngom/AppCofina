<?php

namespace App\Mail;

use App\Models\Habilitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HabilitationPriseEnChargeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Habilitation $habilitation;
    public User $executorIt;

    /**
     * creation de nouvel mail de prise en charge
     */
    public function __construct(Habilitation $habilitation, User $executorIt)
    {
        $this->habilitation = $habilitation;
        $this->executorIt = $executorIt;
    }

    /**
     * Enveloppe du mail
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre demande d\'habilitation a été prise en charge',
        );
    }

    /**
     * Contenu du mail
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.habilitations.prise-en-charge',
        );
    }

    /**
     * Pièces jointes du mail
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
