<x-mail::message>
# Pointage pris en charge

Bonjour,

Le collaborateur que vous avez enrôlé a été enregistré sur la plateforme de pointage.

<x-mail::panel>
**Collaborateur :** {{ $data['prenom'] }} {{ $data['nom'] }}

**Matricule :** {{ $data['matricule'] }}

**Pris en charge par :** {{ $data['it'] ?: 'IT' }}
</x-mail::panel>

<x-mail::button :url="$data['url']">
Voir la fiche
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
