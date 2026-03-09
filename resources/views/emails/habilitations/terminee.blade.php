<x-mail::message>
# Votre demande d'habilitation a été exécutée

Bonjour {{ $data['requester_prenom'] }} {{ $data['requester_nom'] }},

Votre demande d'habilitation a été exécutée par l'équipe IT.

<x-mail::panel>
**Référence :** #{{ $data['habilitation_id'] }}

**Bénéficiaire :** {{ $data['beneficiary_prenom'] }} {{ $data['beneficiary_nom'] }}

**Type de demande :** {{ $data['request_type'] }}

**Exécuteur IT :** {{ $data['executor_name'] }}

**Date d'exécution :** {{ $data['executed_at'] }}

@if(!empty($data['comment_it']))
**Commentaire IT :** {{ $data['comment_it'] }}
@endif
</x-mail::panel>

<x-mail::button :url="$data['url_show']">
Voir la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
