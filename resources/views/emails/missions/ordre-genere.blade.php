<x-mail::message>
# Ordre de mission généré

Bonjour {{ $data['destinataire'] }},

L'ordre de mission **{{ $data['objet'] }}** (N° {{ $data['numero_mission'] ?? '—' }}) a été validé et est disponible.

<x-mail::button :url="$data['pdf_url']">
Télécharger le PDF
</x-mail::button>

Vous pouvez également le retrouver dans votre espace utilisateur (notifications).

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
