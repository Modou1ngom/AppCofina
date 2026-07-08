<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de mission</title>
    <style>
        @page { margin: 2cm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 1.5cm; }
        .logo { width: 140px; margin-bottom: 8px; }
        h1 { font-size: 14pt; text-transform: uppercase; text-decoration: underline; }
        .meta { margin: 1cm 0; }
        .meta td { padding: 4px 8px; vertical-align: top; }
        .section { margin: 0.8cm 0; page-break-inside: avoid; }
        .section h2 { font-size: 11pt; color: #1e40af; margin: 0 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .section .contenu { text-align: justify; white-space: pre-wrap; font-size: 10.5pt; }
        .contenu-global { text-align: justify; margin: 1cm 0; white-space: pre-wrap; }
        .signature { margin-top: 2cm; text-align: right; }
        .signature img { max-width: 200px; max-height: 80px; }
    </style>
</head>
<body>
    <div class="header">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" class="logo" alt="COFINA">
        @endif
        <h1>Rapport de mission</h1>
    </div>

    <table class="meta" width="100%">
        <tr>
            <td width="30%"><strong>Référence</strong></td>
            <td>Mission N° {{ $mission->numero_mission ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Objet</strong></td>
            <td>{{ $mission->objet }}</td>
        </tr>
        <tr>
            <td><strong>Période</strong></td>
            <td>{{ $mission->date_debut?->format('d/m/Y') }} — {{ $mission->date_fin?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Destination</strong></td>
            <td>{{ $mission->perimetre ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Agent signataire</strong></td>
            <td>{{ $signataireNom }}</td>
        </tr>
        <tr>
            <td><strong>Date de soumission</strong></td>
            <td>{{ $mission->rapport_soumis_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <h2 style="font-size: 12pt;">Compte-rendu de mission</h2>

    @if(!empty($sectionsRapport))
        @foreach($sectionsRapport as $section)
            <div class="section">
                <h2>{{ $section['libelle'] }}</h2>
                <div class="contenu">{{ $section['contenu'] }}</div>
            </div>
        @endforeach
    @else
        <div class="contenu-global">{{ $mission->rapport_contenu }}</div>
    @endif

    <div class="signature">
        <p><strong>Signature de l'agent</strong></p>
        @if($mission->rapport_signature_image)
            <img src="{{ $mission->rapport_signature_image }}" alt="Signature">
        @endif
        <p>{{ $signataireNom }}</p>
    </div>
</body>
</html>
