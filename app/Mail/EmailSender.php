<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailSender extends Mailable
{
    use Queueable, SerializesModels;

    /** Sujet de l'email (nom différent de Mailable::$subject pour éviter conflit de type). */
    public string $emailSubject;

    /** Chemin de la vue Blade (ex: emails.habilitations.approuvee). */
    public string $viewPath;

    /**
     * Données passées à la vue Blade (tableau de scalaires/tableaux, pas d'objets Eloquent pour la queue).
     */
    public array $data = [];

    public function __construct(string $subject, string $view, array $data = [])
    {
        $this->emailSubject = $subject;
        $this->viewPath = $view;
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: $this->viewPath,
            with: ['data' => $this->data],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
