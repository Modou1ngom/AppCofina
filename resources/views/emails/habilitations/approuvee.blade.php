<x-mail::message>
# Votre demande d'habilitation a été approuvée

Bonjour {{ $data['requester_prenom'] }} {{ $data['requester_nom'] }},

Votre demande d'habilitation a été approuvée par le Contrôle Permanent.

<x-mail::panel>
**Référence :** #{{ $data['habilitation_id'] }}

**Bénéficiaire :** {{ $data['beneficiary_prenom'] }} {{ $data['beneficiary_nom'] }}

**Type de demande :** {{ $data['request_type'] }}
</x-mail::panel>

La demande va maintenant être prise en charge par l'équipe IT. Vous serez notifié lorsqu'un exécuteur IT l'aura prise en charge.

<x-mail::button :url="$data['url_show']">
Voir la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
