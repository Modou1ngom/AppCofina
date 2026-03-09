<x-mail::message>
# Demande en attente de votre validation (N+1)

Bonjour,

Une demande d'habilitation requiert votre validation en tant que N+1 du demandeur.

<x-mail::panel>
**Référence :** #{{ $data['habilitation_id'] }}

**Demandeur :** {{ $data['requester_prenom'] }} {{ $data['requester_nom'] }}

**Bénéficiaire :** {{ $data['beneficiary_prenom'] }} {{ $data['beneficiary_nom'] }}

**Type de demande :** {{ $data['request_type'] }}
</x-mail::panel>

Merci de vous connecter sur le portail pour traiter cette demande.

<x-mail::button :url="$data['url_show']">
Voir et valider la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
