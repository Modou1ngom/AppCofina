<x-mail::message>
# Mission en attente de traitement

**Objet :** {{ $data['objet'] }}

**Demandeur :** {{ $data['demandeur'] }}

**Étape :** {{ $data['etape'] }}

<x-mail::button :url="$data['action_url']">
Consulter la mission
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
