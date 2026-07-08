<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ordre de mission</title>
    <style>
        @page { margin: 2cm 2.2cm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            line-height: 1.55;
            margin: 0;
        }
        .page {
            position: relative;
        }
        .page-break { page-break-after: always; }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #8B0000;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header > div { display: table-cell; vertical-align: middle; }
        .logo { width: 150px; height: auto; }
        .ref-block { text-align: right; font-size: 9.5pt; color: #475569; }
        .title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #8B0000;
            margin: 0 0 6px;
        }
        .subtitle {
            text-align: center;
            font-size: 10pt;
            color: #64748b;
            margin: 0 0 14px;
        }
        .beneficiaire-box {
            border: 1.5px solid #8B0000;
            background: #fef2f2;
            padding: 10px 12px;
            margin-bottom: 12px;
            text-align: center;
        }
        .beneficiaire-box .nom {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .beneficiaire-box .fonction {
            font-size: 10pt;
            color: #475569;
            margin-top: 4px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .info-grid th,
        .info-grid td {
            border: 1px solid #cbd5e1;
            padding: 7px 9px;
            text-align: left;
            vertical-align: top;
        }
        .info-grid th {
            width: 30%;
            background: #f8fafc;
            font-size: 9pt;
            text-transform: uppercase;
            color: #64748b;
        }
        .info-grid .description-cell {
            font-size: 10pt;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #334155;
        }
        .body-text {
            text-align: justify;
            margin: 6px 0;
            font-size: 10.5pt;
            line-height: 1.45;
        }
        .closing-section {
            page-break-inside: avoid;
            margin-top: 4px;
        }
        .signature-block {
            margin-top: 0.9cm;
            text-align: right;
            padding-right: 0.5cm;
        }
        .signature-label {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer-note {
            margin-top: 1cm;
            font-size: 8.5pt;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>
@foreach($ordres as $ordre)
    <div class="page @if(!$loop->last) page-break @endif">
        <div class="header">
            <div style="width: 55%;">
                @if(!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="COFINA" class="logo">
                @else
                    <div style="font-size: 20pt; color: #8B0000; font-weight: bold;">cofina</div>
                @endif
            </div>
            <div class="ref-block">
                <div><strong>Mission N°</strong> {{ $missionId ?? '—' }}</div>
                <div><strong>Établi le</strong> {{ $dateGeneration ?? now()->format('d/m/Y') }}</div>
            </div>
        </div>

        <h1 class="title">Ordre de mission</h1>
        <p class="subtitle">Document officiel de déplacement professionnel</p>

        <div class="beneficiaire-box">
            <div class="nom">{{ $ordre['nom_complet'] }}</div>
            <div class="fonction">{{ $ordre['fonction'] }}</div>
        </div>

        <table class="info-grid">
            <tr>
                <th>Objet de la mission</th>
                <td><strong>{{ $ordre['objet'] }}</strong></td>
            </tr>
            <tr>
                <th>Destination(s)</th>
                <td>{{ $ordre['destination'] }}</td>
            </tr>
            <tr>
                <th>Période</th>
                <td>Du <strong>{{ $ordre['date_debut'] ?? $ordre['date_mission'] }}</strong> au <strong>{{ $ordre['date_fin'] ?? '—' }}</strong></td>
            </tr>
            <tr>
                <th>Type de déplacement</th>
                <td>{{ ucfirst($ordre['type_deplacement']) }}</td>
            </tr>
            @if(!empty($ordre['description_globale']))
            <tr>
                <th>Description globale</th>
                <td class="description-cell">{{ $ordre['description_globale'] }}</td>
            </tr>
            @endif
        </table>

        <div class="closing-section">
        <p class="body-text">
            La personne désignée ci-dessus est autorisée à se rendre en mission conformément aux informations figurant sur le présent document.
            Tous les frais afférents à son séjour seront supportés par COFINA.
        </p>

        <p class="body-text">
            En foi de quoi, le présent ordre de mission est établi pour servir et valoir ce que de droit.
        </p>

        <div class="signature-block">
            @if(!empty($signatureRrh))
                <img src="{{ $signatureRrh }}" alt="Signature DRH" style="max-height: 64px; max-width: 220px; margin-bottom: 6px; display: block; margin-left: auto;">
            @else
                <div style="display:inline-block;width:220px;border-bottom:1px solid #333;height:28px;margin-bottom:6px;"></div>
            @endif
            <div class="signature-label">Responsable Ressources Humaines</div>
            @if(!empty($signatureRrhNom))
                <div style="font-size: 9pt; margin-top: 4px;">{{ $signatureRrhNom }}</div>
            @endif
            <div style="font-size: 8.5pt; color: #666; font-style: italic; margin-top: 4px;">Signature électronique</div>
        </div>

        <p class="footer-note">
            * À noter qu'il existe une possibilité que cette mission soit prolongée.
        </p>
        </div>
    </div>
@endforeach
</body>
</html>
