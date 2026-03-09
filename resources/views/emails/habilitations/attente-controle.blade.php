<x-mail::message>
# Demande en attente de validation du Contrôle Permanent

Bonjour,

Une demande d'habilitation validée par N+1 et N+2 est en attente de validation du Contrôle Permanent.

<x-mail::panel>
**Référence :** #{{ $data['habilitation_id'] }}

**Demandeur :** {{ $data['requester_prenom'] }} {{ $data['requester_nom'] }}

**Bénéficiaire :** {{ $data['beneficiary_prenom'] }} {{ $data['beneficiary_nom'] }}

**Type de demande :** {{ $data['request_type'] }}
</x-mail::panel>

Merci de vous connecter sur le portail pour prendre en charge cette demande.

<x-mail::button :url="$data['url_show']">
Voir et valider la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
