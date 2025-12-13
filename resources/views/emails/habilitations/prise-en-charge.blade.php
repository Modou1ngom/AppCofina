<x-mail::message>
# Votre demande d'habilitation a été prise en charge

Bonjour {{ $habilitation->requester->prenom }} {{ $habilitation->requester->nom }},

Nous vous informons que votre demande d'habilitation **#{{ $habilitation->id }}** a été prise en charge par l'équipe IT.

**Détails de la demande :**
- **Bénéficiaire :** {{ $habilitation->beneficiary->prenom }} {{ $habilitation->beneficiary->nom }}
- **Type de demande :** {{ $habilitation->request_type }}
- **Exécuteur IT :** {{ $executorIt->name }}
- **Date de prise en charge :** {{ now()->format('d/m/Y à H:i') }}

L'équipe IT procède maintenant à l'exécution de votre demande. Vous serez notifié dès que l'habilitation sera complétée.

<x-mail::button :url="route('habilitations.show', $habilitation->id, true)">
Voir la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
