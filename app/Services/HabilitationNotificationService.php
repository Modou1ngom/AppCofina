<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Models\Habilitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Service d'envoi des notifications par email pour les habilitations.
 * Suit le guide : Job SendEmail + queue asynchrone + données préparées (pas d'Eloquent dans le job).
 */
class HabilitationNotificationService
{
    /**
     * Récupère l'email du demandeur : requester_email (saisi) > profil requester > User lié au profil.
     * Utilise ?: pour que chaîne vide soit traitée comme absent et qu'on utilise le profil/User en secours.
     */
    private static function getRequesterEmail(Habilitation $habilitation): ?string
    {
        $email = trim((string) ($habilitation->requester_email ?? ''))
            ?: $habilitation->requester?->email
            ?: User::whereHas('profil', fn ($q) => $q->where('id', $habilitation->requester_profile_id))->value('email');

        $email = is_string($email) ? trim($email) : $email;
        if (empty($email)) {
            Log::channel('single')->warning('Notification habilitation non envoyée : aucun email pour le demandeur', [
                'habilitation_id' => $habilitation->id,
                'requester_profile_id' => $habilitation->requester_profile_id,
            ]);
            return null;
        }

        return $email;
    }
    /**
     * Construit le tableau $data pour les vues email (scalaires/tableaux uniquement, pour la queue).
     */
    public static function baseData(Habilitation $habilitation): array
    {
        $habilitation->loadMissing(['requester', 'beneficiary']);
        $requester = $habilitation->requester;
        $beneficiary = $habilitation->beneficiary;

        return [
            'habilitation_id' => $habilitation->id,
            'requester_prenom' => $requester?->prenom ?? '',
            'requester_nom' => $requester?->nom ?? '',
            'beneficiary_prenom' => $beneficiary?->prenom ?? '',
            'beneficiary_nom' => $beneficiary?->nom ?? '',
            'request_type' => $habilitation->request_type ?? '',
            'url_show' => URL::route('habilitations.show', ['habilitation' => $habilitation->id]),
            'url_etape2' => URL::route('habilitations.etape2', ['habilitation' => $habilitation->id]),
        ];
    }

    /**
     * Envoi par défaut en queue ; si $sync = true, envoi immédiat (même requête).
     */
    public static function dispatch(string $subject, string|array $to, string $view, array $data, array $cc = [], bool $sync = false): void
    {
        $to = is_array($to) ? array_filter($to) : $to;
        if (empty($to)) {
            Log::channel('single')->warning('Notification habilitation non envoyée : destinataire vide', [
                'subject' => $subject,
                'view' => $view,
            ]);
            return;
        }
        if ($sync) {
            Log::channel('single')->info('Notification habilitation envoyée immédiatement', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
            ]);
            SendEmail::dispatchSync($subject, $to, $cc, [], $view, $data);
        } else {
            Log::channel('single')->info('Notification habilitation mise en queue', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
            ]);
            SendEmail::dispatch($subject, $to, $cc, [], $view, $data);
        }
    }

    /**
     * Notification de création : envoyée immédiatement après validation (pas de queue).
     */
    public static function notifyConfirmationCreation(Habilitation $habilitation): void
    {
        $to = self::getRequesterEmail($habilitation);
        if (! $to) {
            return;
        }
        $data = self::baseData($habilitation);
        self::dispatch(
            'Confirmation : votre demande d\'habilitation a été enregistrée',
            $to,
            'emails.habilitations.confirmation-creation',
            $data,
            [],
            true
        );
    }

    public static function notifyAttenteValidationN1(Habilitation $habilitation): void
    {
        $habilitation->load(['requester.nPlus1']);
        $n1 = $habilitation->requester?->nPlus1;
        $n1Email = $n1?->email ?? ($n1 ? User::whereHas('profil', fn ($q) => $q->where('id', $n1->id))->value('email') : null);
        if (! $n1Email) {
            Log::channel('single')->warning('Notification N+1 non envoyée : aucun email pour le N+1', [
                'habilitation_id' => $habilitation->id,
                'n1_profile_id' => $n1?->id,
            ]);
            return;
        }
        $data = self::baseData($habilitation);
        self::dispatch(
            'Demande d\'habilitation en attente de votre validation (N+1)',
            $n1Email,
            'emails.habilitations.attente-validation-n1',
            $data
        );
    }

    public static function notifyAttenteValidationN2(Habilitation $habilitation): void
    {
        $habilitation->load(['requester.nPlus2']);
        $n2 = $habilitation->requester?->nPlus2;
        $n2Email = $n2?->email ?? ($n2 ? User::whereHas('profil', fn ($q) => $q->where('id', $n2->id))->value('email') : null);
        if (! $n2Email) {
            Log::channel('single')->warning('Notification N+2 non envoyée : aucun email pour le N+2', ['habilitation_id' => $habilitation->id]);
            return;
        }
        $data = self::baseData($habilitation);
        self::dispatch(
            'Demande d\'habilitation en attente de votre validation (N+2)',
            $n2Email,
            'emails.habilitations.attente-validation-n2',
            $data
        );
    }

    public static function notifyAttenteControle(Habilitation $habilitation, array $emailsControle): void
    {
        $data = self::baseData($habilitation);
        $subject = 'Demande d\'habilitation en attente de validation du Contrôle Permanent';
        foreach (array_unique(array_filter($emailsControle)) as $email) {
            self::dispatch($subject, $email, 'emails.habilitations.attente-controle', $data);
        }
    }

    public static function notifyApprouvee(Habilitation $habilitation): void
    {
        $to = self::getRequesterEmail($habilitation);
        if (! $to) {
            return;
        }
        $data = self::baseData($habilitation);
        self::dispatch(
            'Votre demande d\'habilitation a été approuvée',
            $to,
            'emails.habilitations.approuvee',
            $data
        );
    }

    public static function notifyRejetee(Habilitation $habilitation, string $comment): void
    {
        $to = self::getRequesterEmail($habilitation);
        if (! $to) {
            return;
        }
        $data = self::baseData($habilitation);
        $data['comment'] = $comment;
        self::dispatch(
            'Votre demande d\'habilitation a été rejetée',
            $to,
            'emails.habilitations.rejetee',
            $data
        );
    }

    public static function notifyPriseEnCharge(Habilitation $habilitation, string $executorName): void
    {
        $to = self::getRequesterEmail($habilitation);
        if (! $to) {
            return;
        }
        $data = self::baseData($habilitation);
        $data['executor_name'] = $executorName;
        $data['date_prise_en_charge'] = now()->format('d/m/Y à H:i');
        self::dispatch(
            'Votre demande d\'habilitation a été prise en charge',
            $to,
            'emails.habilitations.prise-en-charge',
            $data
        );
    }

    /**
     * @param  bool  $toRequester  Notifier le demandeur (case cochée)
     * @param  bool  $toN1  Notifier le N+1 (case cochée)
     */
    public static function notifyTerminee(Habilitation $habilitation, string $executorName, bool $toRequester = false, bool $toN1 = false): void
    {
        $data = self::baseData($habilitation);
        $data['executor_name'] = $executorName;
        $data['executed_at'] = $habilitation->executed_it_at?->format('d/m/Y à H:i') ?? now()->format('d/m/Y à H:i');
        $data['comment_it'] = $habilitation->comment_it ?? '';

        $subject = 'Votre demande d\'habilitation a été exécutée';

        if ($toRequester) {
            $to = self::getRequesterEmail($habilitation);
            if ($to) {
                self::dispatch($subject, $to, 'emails.habilitations.terminee', $data);
            }
        }

        if ($toN1) {
            $habilitation->load(['requester.nPlus1']);
            $n1 = $habilitation->requester?->nPlus1;
            $n1Email = $n1?->email ?? ($n1 ? User::whereHas('profil', fn ($q) => $q->where('id', $n1->id))->value('email') : null);
            if ($n1Email) {
                self::dispatch($subject, $n1Email, 'emails.habilitations.terminee', $data);
            }
        }
    }
}
