<x-mail::message>
# Signature électronique requise

Bonjour {{ $data['destinataire'] }},

L'ordre de mission **{{ $data['objet'] }}** (N° {{ $data['numero_mission'] ?? '—' }}) a été généré par **{{ $data['generateur'] }}** et nécessite votre signature électronique en tant que Responsable Ressources Humaines.

<x-mail::button :url="$data['pdf_url']">
Consulter et signer l'ordre de mission (PDF)
</x-mail::button>

Vous pouvez également consulter la demande complète :

<x-mail::button :url="$data['mission_url']">
Voir la mission
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
