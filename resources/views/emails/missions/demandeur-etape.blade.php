<x-mail::message>
# Suivi de votre demande de mission

**Objet :** {{ $data['objet'] }}

**Nouvelle étape :** {{ $data['etape'] }}

{{ $data['message'] ?? 'Votre demande a progressé dans le circuit de validation.' }}

<x-mail::button :url="$data['action_url']">
Consulter la mission
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
