<x-mail::message>
# Suivi de votre mission

Bonjour,

Vous êtes concerné(e) en tant que **missionnaire** pour la demande suivante :

**Objet :** {{ $data['objet'] }}

**Demandeur :** {{ $data['demandeur'] }}

**État d'avancement :** {{ $data['etape'] }}

{{ $data['message'] }}

<x-mail::button :url="$data['action_url']">
Consulter la demande et son avancement
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
