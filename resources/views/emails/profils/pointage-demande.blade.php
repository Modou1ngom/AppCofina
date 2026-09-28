<x-mail::message>
# Nouveau staff à enregistrer sur le pointage

Bonjour,

Les Ressources Humaines viennent d'enrôler un collaborateur. Merci de le créer sur la plateforme de pointage, puis de confirmer la prise en charge dans l'application.

<x-mail::panel>
**Collaborateur :** {{ $data['prenom'] }} {{ $data['nom'] }}

**Matricule :** {{ $data['matricule'] }}

**Fonction :** {{ $data['fonction'] ?: '—' }}

**Département :** {{ $data['departement'] ?: '—' }}

**Site :** {{ $data['site'] ?: '—' }}

**E-mail :** {{ $data['email'] ?: '—' }}

**Date d'arrivée :** {{ $data['date_entree'] ?: '—' }}

**Demandé par :** {{ $data['demandeur'] ?: 'RH' }}
</x-mail::panel>

<x-mail::button :url="$data['url']">
Confirmer le pointage
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
