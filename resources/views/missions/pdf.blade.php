<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ordre de mission N° {{ $mission->numero_mission ?? '—' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; color: #1d4ed8; margin-bottom: 4px; }
        .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 10px; margin-bottom: 20px; }
        .section { margin-bottom: 16px; }
        .label { font-weight: bold; color: #64748b; font-size: 10px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ORDRE DE MISSION N° {{ $mission->numero_mission ?? '—' }}</h1>
        <p>{{ $mission->objet }}</p>
        <p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="section">
        <div class="label">Demandeur</div>
        <div>{{ $mission->demandeur?->name ?? '—' }}</div>
    </div>

    <div class="section">
        <div class="label">Période</div>
        <div>Du {{ $mission->date_debut?->format('d/m/Y') }} au {{ $mission->date_fin?->format('d/m/Y') }}</div>
    </div>

    <div class="section">
        <div class="label">Périmètre</div>
        <div>{{ $mission->perimetre }}</div>
    </div>

    <div class="section">
        <div class="label">Description globale</div>
        <div>{{ $mission->description ?? '—' }}</div>
    </div>

    <div class="section">
        <div class="label">Missionnaires</div>
        <table>
            <thead><tr><th>Prénom</th><th>Nom</th></tr></thead>
            <tbody>
                @forelse($participantsAffichage ?? [] as $p)
                    <tr>
                        <td>{{ $p['prenom'] ?? '—' }}</td>
                        <td>{{ $p['nom'] ?? $p['name'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">Aucun missionnaire</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($mission->vehicule_attribue || $mission->logement_attribue)
    <div class="section">
        <div class="label">Logistique</div>
        <p><strong>Véhicule :</strong> {{ $mission->vehicule_attribue ?? '—' }}</p>
        <p><strong>Chauffeur :</strong> {{ $mission->chauffeur?->name ?? '—' }}</p>
        <p><strong>Hébergement :</strong> {{ $mission->logement_attribue ?? '—' }}</p>
        <p><strong>Total logistique estimé :</strong> {{ number_format($mission->totalLogistique(), 0, ',', ' ') }} XOF</p>
    </div>
    @endif
</body>
</html>
