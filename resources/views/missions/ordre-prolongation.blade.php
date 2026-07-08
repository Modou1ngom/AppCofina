<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ordre de prolongation de mission</title>
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
        .page { position: relative; }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #8B0000;
            padding-bottom: 12px;
            margin-bottom: 20px;
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
            margin: 0 0 18px;
        }
        .subtitle {
            text-align: center;
            font-size: 10pt;
            color: #64748b;
            margin: -12px 0 20px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .info-grid th,
        .info-grid td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        .info-grid th {
            width: 34%;
            background: #f8fafc;
            font-size: 9pt;
            text-transform: uppercase;
            color: #64748b;
        }
        .body-text {
            text-align: justify;
            margin: 14px 0;
        }
        .motif-box {
            border: 1px solid #cbd5e1;
            background: #fffbeb;
            padding: 10px 12px;
            border-radius: 4px;
            margin: 12px 0 18px;
        }
        .closing-section {
            page-break-inside: avoid;
            margin-top: 8px;
        }
        .signature-block {
            margin-top: 1.2cm;
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
    <div class="page">
        <div class="header">
            <div style="width: 55%;">
                @if(!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="COFINA" class="logo">
                @else
                    <div style="font-size: 20pt; color: #8B0000; font-weight: bold;">cofina</div>
                @endif
            </div>
            <div class="ref-block">
                <div><strong>Mission N°</strong> {{ $mission->numero_mission ?? '—' }}</div>
                <div><strong>Établi le</strong> {{ $dateGeneration }}</div>
            </div>
        </div>

        <h1 class="title">Ordre de prolongation de mission</h1>
        <p class="subtitle">Modification de la période initiale — signature du Responsable RH requise</p>

        <table class="info-grid">
            <tr>
                <th>Objet de la mission</th>
                <td><strong>{{ $mission->objet }}</strong></td>
            </tr>
            <tr>
                <th>Demandeur</th>
                <td>{{ $demandeur }}</td>
            </tr>
            <tr>
                <th>Destination(s)</th>
                <td>{{ $destination }}</td>
            </tr>
            <tr>
                <th>Période initiale</th>
                <td>Du <strong>{{ $ancienDebut }}</strong> au <strong>{{ $ancienFin }}</strong></td>
            </tr>
            <tr>
                <th>Nouvelle période</th>
                <td>Du <strong>{{ $nouveauDebut }}</strong> au <strong>{{ $nouveauFin }}</strong></td>
            </tr>
            <tr>
                <th>Missionnaires concernés</th>
                <td>{{ $missionnaires }}</td>
            </tr>
        </table>

        <p class="body-text">
            Par le présent ordre, il est autorisé de <strong>prolonger</strong> la mission ci-dessus selon la nouvelle période indiquée.
            Les frais afférents au séjour complémentaire restent à la charge de COFINA, sous réserve des validations logistique et financière déjà effectuées.
        </p>

        <div class="motif-box">
            <strong>Motif de la prolongation :</strong><br>
            {{ $motif }}
        </div>

        @if(!empty($descriptionGlobale))
            <p class="body-text">
                <strong>Description globale de la mission :</strong> {{ $descriptionGlobale }}
            </p>
        @endif

        @if(!empty($sitesProlongation))
            <table class="info-grid" style="margin-top: 12px;">
                <tr>
                    <th colspan="2" style="background: #fffbeb; color: #92400e;">Sites concernés par la prolongation</th>
                </tr>
                @foreach($sitesProlongation as $site)
                    <tr>
                        <th>{{ $site }}</th>
                        <td>{{ $descriptionsSitesProlongation[$site] ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <div class="closing-section">
        <p class="body-text">
            En foi de quoi, le présent ordre de prolongation est établi pour servir et valoir ce que de droit.
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
            Document généré par le module Gestion des missions — COFINA.
        </p>
        </div>
    </div>
</body>
</html>
