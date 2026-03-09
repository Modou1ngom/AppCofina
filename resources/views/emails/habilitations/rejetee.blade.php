<x-mail::message>
# Votre demande d'habilitation a été rejetée

Bonjour {{ $data['requester_prenom'] }} {{ $data['requester_nom'] }},

Votre demande d'habilitation a été rejetée.

<x-mail::panel>
**Référence :** #{{ $data['habilitation_id'] }}

**Bénéficiaire :** {{ $data['beneficiary_prenom'] }} {{ $data['beneficiary_nom'] }}

**Type de demande :** {{ $data['request_type'] }}

@if(!empty($data['comment']))
**Motif du rejet :** {{ $data['comment'] }}
@endif
</x-mail::panel>

<x-mail::button :url="$data['url_show']">
Voir la demande
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
