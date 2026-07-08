<x-mail::message>
# Rapport de mission à valider

**Objet :** {{ $data['objet'] }}

**Signataire du rapport :** {{ $data['signataire'] }}

Un rapport de mission signé a été soumis et attend votre validation pour clôturer officiellement la mission.

<x-mail::button :url="$data['action_url']">
Valider le rapport
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
