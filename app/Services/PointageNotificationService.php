<?php

namespace App\Services;

use App\Models\Pointage;
use App\Models\PointageDeclaration;
use App\Models\Profil;
use App\Models\User;
use App\Notifications\PointageDatabaseNotification;
use Illuminate\Support\Collection;

class PointageNotificationService
{
    public static function userForProfil(?Profil $profil): ?User
    {
        if ($profil === null) {
            return null;
        }

        $email = strtolower(trim((string) $profil->email));
        if ($email === '') {
            return null;
        }

        return User::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public static function rhAndAdminUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($q): void {
                $q->whereIn('slug', ['rh', 'admin', 'super_admin']);
            })
            ->get();
    }

    public static function notifyDeclarationSubmitted(PointageDeclaration $declaration): void
    {
        $declaration->loadMissing(['user.profil']);
        $declarer = $declaration->user;
        if ($declarer === null) {
            return;
        }

        $date = $declaration->date_concernee->format('d/m/Y');
        $declarerName = $declarer->name;

        $declarer->notify(new PointageDatabaseNotification(
            'Déclaration de pointage envoyée',
            "Votre déclaration pour le {$date} a été enregistrée et transmise à votre manager.",
            [
                'kind' => 'declaration_submitted',
                'pointage_declaration_id' => $declaration->id,
                'url' => '/pointage/declarations',
            ]
        ));

        $managerProfil = $declarer->profil?->nPlus1;
        $manager = self::userForProfil($managerProfil);
        if ($manager !== null && $manager->id !== $declarer->id) {
            $manager->notify(new PointageDatabaseNotification(
                'Déclaration de pointage à valider',
                "{$declarerName} a soumis une déclaration pour le {$date}.",
                [
                    'kind' => 'declaration_pending_manager',
                    'pointage_declaration_id' => $declaration->id,
                    'url' => '/pointage/declarations/validation-manager',
                ]
            ));
        }
    }

    public static function notifyManagerDecision(PointageDeclaration $declaration, bool $approved): void
    {
        $declaration->loadMissing(['user']);
        $declarer = $declaration->user;
        if ($declarer === null) {
            return;
        }

        $date = $declaration->date_concernee->format('d/m/Y');

        if ($approved) {
            $declarer->notify(new PointageDatabaseNotification(
                'Déclaration validée par le manager',
                "Votre déclaration du {$date} a été transmise aux RH pour validation finale.",
                [
                    'kind' => 'declaration_pending_rh',
                    'pointage_declaration_id' => $declaration->id,
                    'url' => '/pointage/declarations',
                ]
            ));

            foreach (self::rhAndAdminUsers() as $rh) {
                if ($rh->id === $declarer->id) {
                    continue;
                }
                $rh->notify(new PointageDatabaseNotification(
                    'Déclaration de pointage — validation RH',
                    "Déclaration de {$declarer->name} pour le {$date} en attente de validation RH.",
                    [
                        'kind' => 'declaration_pending_rh',
                        'pointage_declaration_id' => $declaration->id,
                        'url' => '/pointage/declarations/validation-rh',
                    ]
                ));
            }
        } else {
            $comment = trim((string) $declaration->commentaire_manager);
            $body = "Votre déclaration du {$date} a été rejetée par votre manager.";
            if ($comment !== '') {
                $body .= ' Commentaire : '.$comment;
            }

            $declarer->notify(new PointageDatabaseNotification(
                'Déclaration rejetée (manager)',
                $body,
                [
                    'kind' => 'declaration_rejected_manager',
                    'pointage_declaration_id' => $declaration->id,
                    'url' => '/pointage/declarations',
                ]
            ));
        }
    }

    public static function notifyRhDecision(PointageDeclaration $declaration, bool $approved): void
    {
        $declaration->loadMissing(['user']);
        $declarer = $declaration->user;
        if ($declarer === null) {
            return;
        }

        $date = $declaration->date_concernee->format('d/m/Y');
        $comment = trim((string) $declaration->commentaire_rh);

        if ($approved) {
            $body = "Votre déclaration du {$date} a été approuvée par les RH.";
            $declarer->notify(new PointageDatabaseNotification(
                'Déclaration approuvée',
                $body,
                [
                    'kind' => 'declaration_approved',
                    'pointage_declaration_id' => $declaration->id,
                    'url' => '/pointage/declarations',
                ]
            ));
        } else {
            $body = "Votre déclaration du {$date} a été rejetée par les RH.";
            if ($comment !== '') {
                $body .= ' Commentaire : '.$comment;
            }

            $declarer->notify(new PointageDatabaseNotification(
                'Déclaration rejetée (RH)',
                $body,
                [
                    'kind' => 'declaration_rejected_rh',
                    'pointage_declaration_id' => $declaration->id,
                    'url' => '/pointage/declarations',
                ]
            ));
        }
    }

    public static function notifyAttendanceRecorded(User $user, Pointage $pointage, string $typeLabel): void
    {
        $pointage->loadMissing('site');
        $siteNom = $pointage->site?->nom ?? 'site';
        $heure = $pointage->enregistre_at->timezone(config('app.timezone'))->format('H:i');

        $user->notify(new PointageDatabaseNotification(
            'Pointage enregistré',
            "{$typeLabel} enregistré à {$heure} — {$siteNom}.",
            [
                'kind' => $typeLabel === 'Sortie' ? 'checkout' : 'checkin',
                'pointage_id' => $pointage->id,
                'url' => '/pointage',
            ]
        ));
    }
}
