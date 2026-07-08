<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande de validation de mission</title>
    <style>
        @page { margin: 1.2cm 1.4cm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .bordered { border: 1.5px solid #000; }
        .header-top {
            display: table;
            width: 100%;
            border: 1.5px solid #000;
            border-bottom: none;
            page-break-inside: avoid;
        }
        .header-top > div {
            display: table-cell;
            vertical-align: middle;
            padding: 8px 10px;
            border-right: 1.5px solid #000;
        }
        .header-top > div:last-child { border-right: none; }
        .logo { width: 120px; height: auto; }
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
        }
        .section-title {
            background: #8B0000;
            color: #fff;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            padding: 5px 8px;
            font-size: 9pt;
            border: 1.5px solid #000;
            border-top: none;
            page-break-after: avoid;
        }
        .grid-2 {
            display: table;
            width: 100%;
            border-left: 1.5px solid #000;
            border-right: 1.5px solid #000;
            page-break-inside: auto;
        }
        .grid-2 > div {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 8px 10px;
            page-break-inside: avoid;
        }
        .grid-2 > div:first-child { border-right: 1.5px solid #000; }
        .beneficiaires-col {
            page-break-inside: auto;
        }
        .label { font-weight: bold; }
        .line {
            border-bottom: 1px dotted #333;
            min-height: 16px;
            margin: 3px 0 8px;
            padding-bottom: 2px;
        }
        .benef-row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .benef-row > span {
            display: table-cell;
            width: 50%;
            padding-right: 8px;
        }
        .details-block {
            page-break-inside: avoid;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1.5px solid #000;
            border-right: 1.5px solid #000;
        }
        .details-table td {
            padding: 6px 10px;
            border-bottom: 1px dotted #666;
            vertical-align: top;
        }
        .details-table td:first-child {
            width: 38%;
            font-weight: bold;
        }
        .motif-section {
            page-break-inside: auto;
        }
        .motif-box {
            border: 1.5px solid #000;
            border-top: none;
            min-height: 36px;
            padding: 8px 10px;
            text-align: justify;
            white-space: pre-wrap;
            word-wrap: break-word;
            orphans: 3;
            widows: 3;
        }
        .signatures-wrap {
            page-break-inside: avoid;
            page-break-before: auto;
        }
        .signatures {
            display: table;
            width: 100%;
            border: 1.5px solid #000;
            border-top: none;
        }
        .sign-col {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            padding: 8px;
            border-right: 1.5px solid #000;
            text-align: center;
        }
        .sign-col:last-child { border-right: none; }
        .sign-col h4 {
            margin: 0 0 8px;
            font-size: 8.5pt;
            text-transform: uppercase;
        }
        .sign-img {
            max-width: 140px;
            max-height: 55px;
            margin: 4px auto;
            display: block;
        }
        .sign-meta { font-size: 8pt; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header-top">
        <div style="width: 28%;">
            @if($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="COFINA" class="logo">
            @else
                <strong style="color:#8B0000;font-size:14pt;">cofina</strong>
            @endif
        </div>
        <div style="width: 44%;" class="header-title">
            Fiche validation mission
        </div>
        <div style="width: 28%;">
            <span class="label">Filiale :</span>
            <div class="line">{{ $filiale }}</div>
        </div>
    </div>

    <div class="section-title">Demande de validation de mission</div>

    <div class="grid-2 bordered" style="border-top:none;border-left:1.5px solid #000;border-right:1.5px solid #000;">
        <div>
            <div class="label" style="margin-bottom:6px;">Direction / Département / Service :</div>
            <div class="line">{{ $departement }}</div>
        </div>
        <div></div>
    </div>

    <div class="grid-2" style="border-bottom:1.5px solid #000;">
        <div>
            <div class="label" style="margin-bottom:6px;">Demandeur</div>
            <div class="label">Nom &amp; Prénom :</div>
            <div class="line">{{ $demandeur['nom'] }}</div>
            <div class="label">Fonction :</div>
            <div class="line">{{ $demandeur['fonction'] }}</div>
            <div class="label">E-mail :</div>
            <div class="line">{{ $demandeur['email'] }}</div>
            <div class="label">Téléphone :</div>
            <div class="line">{{ $demandeur['telephone'] }}</div>
        </div>
        <div class="beneficiaires-col">
            <div class="label" style="margin-bottom:6px;">Bénéficiaires de la mission</div>
            @forelse($beneficiaires as $b)
                <div class="benef-row">
                    <span>
                        <span class="label">Nom &amp; Prénom :</span>
                        <div class="line">{{ $b['nom'] }}</div>
                    </span>
                    <span>
                        <span class="label">Fonction :</span>
                        <div class="line">{{ $b['fonction'] }}</div>
                    </span>
                </div>
            @empty
                <p style="margin:0;font-size:8.5pt;color:#555;">Aucun missionnaire renseigné.</p>
            @endforelse
        </div>
    </div>

    <div class="details-block">
        <div class="section-title">Détails de la mission</div>
        <table class="details-table">
            <tr>
                <td>Date de la demande :</td>
                <td>{{ $date_demande }}</td>
            </tr>
            <tr>
                <td>Destination(s) :</td>
                <td>{{ $destination }}</td>
            </tr>
            <tr>
                <td>Objet de la Mission :</td>
                <td>{{ $objet }}</td>
            </tr>
            <tr>
                <td>Durée de la Mission :</td>
                <td>{{ $duree }}</td>
            </tr>
            <tr>
                <td>Date de Début de la Mission :</td>
                <td>{{ $date_debut }}</td>
            </tr>
            <tr>
                <td>Date de Fin de la Mission :</td>
                <td>{{ $date_fin }}</td>
            </tr>
        </table>
    </div>

    <div class="motif-section">
        <div class="section-title">Motif de la demande (description globale)</div>
        <div class="motif-box">{{ $motif }}</div>
    </div>

    <div class="signatures-wrap">
        <div class="section-title">Validation de la demande</div>
        <div class="signatures">
            <div class="sign-col">
                <h4>N+1 du demandeur</h4>
                <div class="sign-meta"><strong>{{ $signatures['n1']['nom'] ?? '—' }}</strong></div>
                <div class="sign-meta">Date : {{ $signatures['n1']['date'] ?? '—' }}</div>
                @if(!empty($signatures['n1']['image']))
                    <img src="{{ $signatures['n1']['image'] }}" class="sign-img" alt="Signature N+1">
                @else
                    <div class="line" style="margin-top:20px;">Signature :</div>
                @endif
            </div>
            <div class="sign-col">
                <h4>DGA Support</h4>
                <div class="sign-meta"><strong>{{ $signatures['dga']['nom'] ?? '—' }}</strong></div>
                <div class="sign-meta">Date : {{ $signatures['dga']['date'] ?? '—' }}</div>
                @if(!empty($signatures['dga']['image']))
                    <img src="{{ $signatures['dga']['image'] }}" class="sign-img" alt="Signature DGA">
                @else
                    <div class="line" style="margin-top:20px;">Signature :</div>
                @endif
            </div>
            <div class="sign-col">
                <h4>MD</h4>
                <div class="sign-meta"><strong>{{ $signatures['md']['nom'] ?? '—' }}</strong></div>
                <div class="sign-meta">Date : {{ $signatures['md']['date'] ?? '—' }}</div>
                @if(!empty($signatures['md']['image']))
                    <img src="{{ $signatures['md']['image'] }}" class="sign-img" alt="Signature MD">
                @else
                    <div class="line" style="margin-top:20px;">Signature :</div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
