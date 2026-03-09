<x-mail::message>
# Demande d'habilitation enregistrée

Bonjour {{ $data['requester_prenom'] }} {{ $data['requester_nom'] }},

Votre demande d'habilitation a bien été enregistrée.

<x-mail::panel>
**Référence :** #{{ $data['habilitation_id'] }}

**Bénéficiaire :** {{ $data['beneficiary_prenom'] }} {{ $data['beneficiary_nom'] }}

**Type de demande :** {{ $data['request_type'] }}
</x-mail::panel>

Votre demande a été transmise à votre N+1 pour validation. Vous serez notifié des prochaines étapes.

<x-mail::button :url="$data['url_show']">
Voir la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
