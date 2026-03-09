<x-mail::message>
# Demande en attente de votre validation (N+2)

Bonjour,

Une demande d'habilitation validée par le N+1 requiert maintenant votre validation en tant que N+2 du demandeur.

<x-mail::panel>
**Référence :** #{{ $data['habilitation_id'] }}

**Demandeur :** {{ $data['requester_prenom'] }} {{ $data['requester_nom'] }}

**Bénéficiaire :** {{ $data['beneficiary_prenom'] }} {{ $data['beneficiary_nom'] }}

**Type de demande :** {{ $data['request_type'] }}
</x-mail::panel>

Merci de vous connecter sur le portail pour valider cette demande.

<x-mail::button :url="$data['url_show']">
Voir et valider la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
