<x-mail::message>
# Rappel — Mission en attente

**Objet :** {{ $data['objet'] }}

**Demandeur :** {{ $data['demandeur'] }}

**Étape en attente :** {{ $data['etape'] }}

Cette demande attend toujours votre intervention depuis plus de 12 heures.

<x-mail::button :url="$data['action_url']">
Traiter la mission
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
