<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche d'habilitations #{{ $habilitation->id }}</title>
    <style>
        @font-face {
            font-family: 'DejaVu Sans';
            src: url('https://fonts.googleapis.com/css2?family=Noto+Sans+Symbols+2&display=swap');
        }
        @page {
            margin: 3mm 8mm 3mm 8mm;
            size: A4;
        }
        * {
            margin: 2;
            padding: 2;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 8.5pt;
            line-height: 1.15;
            color: #2c3e50;
            background-color: #ffffff;
            border: 2px solid #000;
            padding: 2mm;
            margin: 0;
        }
        .header-container {
            display: table;
            width: 100%;
            margin-top: 0;
            margin-bottom: 2px;
            border-bottom: 2px solid #DC143C;
            padding-bottom: 2px;
        }
        .header-left {
            display: table-cell;
            width: 30%;
            vertical-align: top;
            padding-right: 15px;
        }
        .header-center {
            display: table-cell;
            width: 40%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .header-right {
            display: table-cell;
            width: 30%;
            text-align: right;
            vertical-align: top;
            font-size: 8pt;
            color: #000;
            padding-left: 15px;
        }
        .logo {
            font-size: 20pt;
            font-weight: bold;
            color: #DC143C;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }
        .logo-img {
            max-width: 120px;
            max-height: 60px;
            width: auto;
            height: auto;
            display: block;
            margin-bottom: 5px;
        }
        .logo-reference {
            font-size: 7pt;
            color: #666;
            margin-top: 3px;
        }
        .main-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
            background-color: #DC143C;
            color: white;
            padding: 6px 12px;
        }
        .subtitle {
            font-size: 9pt;
            font-weight: normal;
            margin-top: 3px;
            color: #000;
        }
        .date-info {
            margin-bottom: 4px;
            font-size: 8pt;
            line-height: 1.3;
        }
        .date-info strong {
            color: #000;
        }
        .two-columns {
            display: table;
            width: 100%;
            margin-top: 2px;
            margin-bottom: 1px;
            border-collapse: separate;
            border-spacing: 10px 0;
        }
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 0 5px;
        }
        .section-header {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #DC143C;
            color: white;
            padding: 2px 8px;
            margin-bottom: 2px;
            margin-top: 1px;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .field-row {
            margin-bottom: 2px;
            min-height: 13px;
            padding: 0;
        }
        .field-label {
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
            font-size: 10pt;
            color: #000;
        }
        .field-value {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 150px;
            padding: 0 3px;
            font-size: 8.5pt;
            color: #000;
        }
        .field-value-long {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 250px;
            padding: 0 3px;
            font-size: 10pt;
            color: #000;
        }
        .checkbox-container {
            margin: 6px 0;
            padding: 0;
        }
        .checkbox-row {
            margin: 3px 0;
            display: inline-block;
            width: 32%;
            vertical-align: top;
            padding: 2px 0;
            margin-right: 1%;
        }
        .checkbox-box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1.5px solid #000;
            margin-right: 5px;
            vertical-align: middle;
            position: relative;
            background-color: #fff;
        }
        .checkbox-box.checked {
            background-color: #000;
        }
        .checkbox-box.checked::before {
            content: "✓";
            position: absolute;
            top: -2px;
            left: 1px;
            font-size: 9px;
            font-weight: bold;
            line-height: 10px;
            color: white;
        }
        .validation-icon {
            display: inline-block;
            color: #28a745;
            font-size: 16px;
            font-weight: bold;
            margin-right: 6px;
            vertical-align: middle;
            line-height: 1;
            font-family: "DejaVu Sans", Arial, sans-serif;
        }
        .checkbox-text {
            display: inline-block;
            vertical-align: middle;
            font-size: 10pt;
            color: #000;
        }
        .checkbox-text-small {
            font-size: 7.5pt;
        }
        .note-text {
            font-size: 7.5pt;
            font-style: italic;
            margin-top: 6px;
            margin-bottom: 8px;
            color: #000;
            padding: 0;
        }
        .text-box {
            border: 1px solid #000;
            min-height: 35px;
            padding: 2px;
            margin-top: 2px;
            margin-bottom: 2px;
            font-size: 8.5pt;
            background-color: #fff;
            color: #000;
        }
        .validation-container {
            margin-top: 10px;
        }
        .validation-columns {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 3px;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .validation-column {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            border: 1px solid #000;
            padding: 3px 5px;
            border-right: none;
            background-color: #ffffff;
        }
        .validation-column:last-child {
            border-right: 1px solid #000;
        }
        .validation-title {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
            text-align: center;
            background-color: #DC143C;
            color: white;
            padding: 3px;
            margin: -4px -5px 4px -5px;
            letter-spacing: 0.3px;
        }
        .signature-area {
            margin-top: 8px;
            min-height: 18px;
            width: 100%;
            margin-bottom: 2px;
            background-color: #fff;
        }
        .signature-label {
            text-align: center;
            font-size: 7pt;
            margin-top: 3px;
            color: #000;
            font-weight: normal;
        }
        .other-app-text {
            display: inline-block;
            margin-left: 6px;
            font-size: 8.5pt;
            border-bottom: 1px solid #000;
            min-width: 150px;
            padding: 0 2px;
        }
        .selected-apps {
            margin-top: 4px;
            margin-left: 15px;
            font-size: 8.5pt;
        }
        .footer-instructions {
            margin-top: 15px;
            font-size: 7.5pt;
            color: #000;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header-container">
        <div class="header-left">
            @php
                $logoPath = null;
                $logoUrl = null;
                
                // Essayer d'abord avec .png
                if (file_exists(public_path('logo_Cofina.png'))) {
                    $logoPath = public_path('logo_Cofina.png');
                    $imageData = file_get_contents($logoPath);
                    $logoUrl = 'data:image/png;base64,' . base64_encode($imageData);
                }
                // Sinon essayer avec .jpg
                elseif (file_exists(public_path('logo_Cofina.jpg'))) {
                    $logoPath = public_path('logo_Cofina.jpg');
                    $imageData = file_get_contents($logoPath);
                    $logoUrl = 'data:image/jpeg;base64,' . base64_encode($imageData);
                }
            @endphp
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo COFINA" class="logo-img" />
            @else
                <div class="logo">cofina</div>
            @endif
            <div class="logo-reference">02SUP01V05</div>
        </div>
        <div class="header-center">
            <div class="main-title">DEMANDE D'HABILITATIONS</div>
        </div>
        <div class="header-right">
            <!--<div class="date-info"><strong>Date de création:</strong> {{ $habilitation->created_at->format('d/m/y') }}</div>-->
            <div class="date-info"><strong>Date de création:</strong> 09/12/2025</div>

            <div class="date-info">Fiche d'habilitations</div>
            <div class="date-info"><strong>Filiale</strong> {{ $habilitation->subsidiary ?? '' }}</div>
        </div>
    </div>

    <!-- DEMANDEUR et BÉNÉFICIAIRE -->
    <div class="two-columns">
        <!-- Colonne gauche: DEMANDEUR -->
        <div class="column">
            <div class="section-header">DEMANDEUR</div>
            <div class="field-row">
                <span class="field-label">Direction / Département / Service:</span>
                <span class="field-value"style="border-bottom: none;">{{ $habilitation->requester_direction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Nom & Prénom:</span>
                <span class="field-value" style="border-bottom: none;">{{ $habilitation->requester->prenom }} {{ $habilitation->requester->nom }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Fonction:</span>
                <span class="field-value"style="border-bottom: none;">{{ $habilitation->requester->fonction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">E-mail:</span>
                <span class="field-value"style="border-bottom: none;">{{ $habilitation->requester_email ?? $habilitation->requester->email ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Téléphone:</span>
                <span class="field-value"style="border-bottom: none;">{{ $habilitation->requester_telephone ?? $habilitation->requester->telephone ?? '' }}</span>
            </div>
        </div>

        <!-- Colonne droite: BÉNÉFICIAIRE -->
        <div class="column">
            <div class="section-header">BÉNÉFICIAIRE</div>
            <div class="field-row">
                <span class="field-label">Direction / Département / Service:</span>
                <span class="field-value" style="border-bottom: none;">{{ $habilitation->beneficiary_direction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Nom & Prénom:</span>
                <span class="field-value" style="border-bottom: none;">{{ $habilitation->beneficiary->prenom }} {{ $habilitation->beneficiary->nom }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Fonction:</span>
                <span class="field-value"style="border-bottom: none;">{{ $habilitation->beneficiary->fonction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">E-mail:</span>
                <span class="field-value"style="border-bottom: none;">{{ $habilitation->beneficiary_email ?? $habilitation->beneficiary->email ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Site:</span>
                <span class="field-value"style="border-bottom: none;"   >{{ $habilitation->beneficiary_site ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Téléphone:</span>
                <span class="field-value"style="border-bottom: none;"   >{{ $habilitation->beneficiary_telephone ?? $habilitation->beneficiary->telephone ?? '' }}</span>
            </div>
        </div>
    </div>

    <!-- DÉTAILS DE LA DEMANDE -->
    <div class="section-header" style="margin-top: 3px;">DÉTAILS DE LA DEMANDE</div>

    <!-- Type de la demande -->
    <div style="margin: 2px 0; padding-left: 10px;">
        <strong style="font-size: 8.5pt;">Type de la demande:</strong>
        <div style="margin-left: 10px; margin-top: 2px;">
            <span class="validation-icon">✓</span>
            <span class="checkbox-text">
                @if($habilitation->request_type === 'Creation') Création
                @elseif($habilitation->request_type === 'Modification') Modification
                @elseif($habilitation->request_type === 'Desactivation') Désactivation
                @elseif($habilitation->request_type === 'Suppression') Suppression
                @else {{ $habilitation->request_type }}
                @endif
            </span>
        </div>
    </div>

    <!-- Type d'application ou service - UNIQUEMENT LES APPLICATIONS SÉLECTIONNÉES -->
    <div style="margin: 2px 0; padding-left: 10px;">
        <strong style="font-size: 8.5pt;">Type d'application ou service:</strong>
        <div class="checkbox-container" style="margin-left: 10px; margin-top: 3px; margin-bottom: 3px;">
            @if($habilitation->applications && count($habilitation->applications) > 0)
                @foreach($habilitation->applications as $app)
                <div class="checkbox-row" style="margin-left: 0;">
                    <span class="validation-icon">✓</span>
                    <span class="checkbox-text">{{ $app }}</span>
                </div>
                @endforeach
            @endif
            @if($habilitation->other_application)
            <div style="margin: 3px 0;">
                <span class="validation-icon">✓</span>
                <span class="checkbox-text">Autre (préciser)</span>
                <span class="other-app-text">{{ $habilitation->other_application }}</span>
            </div>
            @endif
        </div>
        <div class="field-row">
            <span class="field-label">Autre (préciser):</span>
            <span class="field-value-long"style="border-bottom: none;">{{ $habilitation->other_application ?? '' }}</span>
        </div>
    </div>

    <!-- Bande rouge -->
    <div class="section-header" style="margin: 4px -5px; padding: 3px; background-color: #DC143C; color: white; font-size: 8.5pt; font-weight: bold; letter-spacing: 0.3px;">
    </div>

    <!-- Autres champs -->
    <div style="padding-left: 10px; margin-top: 2px;">
      
        <div class="field-row">
            <span class="field-label">Profil Actuel (préciser):</span>
            <span class="field-value-long"style="border-bottom: none;">{{ $habilitation->current_profile ?? '' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Profil demandé (préciser):</span>
            <span class="field-value-long"style="border-bottom: none;">{{ $habilitation->requested_profile ?? '' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Date d'implémentation souhaitée:</span>
            <span class="field-value-long"style="border-bottom: none;">{{ $habilitation->desired_implementation_date ? \Carbon\Carbon::parse($habilitation->desired_implementation_date)->format('d/m/Y') : '' }}</span>
        </div>
    </div>
<!-- Bande rouge -->
<div class="section-header" style="margin: 4px -5px; padding: 3px; background-color: #DC143C; color: white; font-size: 8.5pt; font-weight: bold; letter-spacing: 0.3px;">
    </div>
    <!-- Type de profil -->
    <div style="margin: 2px 0; padding-left: 10px;">
        <strong style="font-size: 10pt;">Type de profil:</strong>
        <div style="margin-left: 10px; margin-top: 1px;">
            @if($habilitation->profile_type)
            <div>
                <span class="validation-icon">✓</span>
                <span class="checkbox-text">{{ $habilitation->profile_type }}</span>
            </div>
            @if($habilitation->specific_profile)
            <div style="margin-left: 15px; margin-top: 2px; font-size: 9pt;">{{ $habilitation->specific_profile }}</div>
            @endif
            @endif
        </div>
    </div>

    <!-- Période de validité -->
    <div style="margin: 2px 0; padding-left: 10px;">
        <strong style="font-size: 10pt;">Période de validité:</strong>
        <div style="margin-left: 10px; margin-top: 1px;">
            @if($habilitation->validity_period === 'Permanent')
            <div>
                <span class="validation-icon">✓</span>
                <span class="checkbox-text">Permanent</span>
            </div>
            @elseif($habilitation->validity_period === 'Temporaire')
            <div>
                <span class="validation-icon">✓</span>
                <span class="checkbox-text checkbox-text-small">Temporaire: du 
                    @if($habilitation->start_date)
                        {{ \Carbon\Carbon::parse($habilitation->start_date)->format('d/m/Y') }}
                    @else
                        __/__/____
                    @endif
                    au 
                    @if($habilitation->end_date)
                        {{ \Carbon\Carbon::parse($habilitation->end_date)->format('d/m/Y') }}
                    @else
                        __/__/____
                    @endif
                </span>
            </div>
            @endif
        </div>
    </div>

    <div class="note-text" style="margin-bottom: 4px;">
        <strong>NB:</strong> "pour les demandes standards (logiciel bureautique, compte Windows) la validation du contrôle interne n'est pas nécessaire"
    </div>

    <!-- MOTIF DE LA DEMANDE -->
    <div class="section-header">MOTIF DE LA DEMANDE</div>
    <div class="text-box">{{ $habilitation->request_reason ?? '' }}</div>

    <!-- VALIDATION DE LA DEMANDE -->
    <div class="section-header" style="margin-left: 0; margin-right: 0;">VALIDATION DE LA DEMANDE</div>

    <!-- Trois colonnes côte à côte -->
    <div class="validation-columns">
        <!-- N+1 du demandeur -->
        <div class="validation-column">
            <div class="validation-title">N+1 du demandeur</div>
            <div class="field-row">
                <span class="field-label">Nom et Prénoms:</span>
            </div>
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%; border-bottom: none;">{{ $habilitation->validatorN1->name ?? '' }}</div>
            <div class="field-row" style="display: flex; align-items: baseline;">
                <span class="field-label" style="margin-right: 5px;">Date:</span>
                <span class="field-value" style="border-bottom: none; display: inline-block;">{{ $habilitation->validated_n1_at ? \Carbon\Carbon::parse($habilitation->validated_n1_at)->format('d/m/Y') : '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Signature:</span>
            </div>
            @if($habilitation->signature_n1)
                <div style="margin-top: 3px; margin-bottom: 2px;">
                    <img src="{{ $habilitation->signature_n1 }}" alt="Signature N+1" style="max-width: 100%; max-height: 40px;" />
                </div>
            @else
                <div class="signature-area"></div>
            @endif
            @if($habilitation->comment_n1)
            <div style="margin-top: 3px; margin-bottom: 0; font-size: 7.5pt;">
                <strong>Commentaire:</strong> {{ $habilitation->comment_n1 }}
            </div>
            @endif
        </div>

        <!-- N+2 du demandeur -->
        <div class="validation-column">
            <div class="validation-title">N+2 du demandeur</div>
            <div class="field-row">
                <span class="field-label">Nom et Prénoms:</span>
            </div>
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%; border-bottom: none;">{{ $habilitation->validatorN2->name ?? '' }}</div>
            <div class="field-row" style="display: flex; align-items: baseline;">
                <span class="field-label" style="margin-right: 5px;">Date:</span>
                <span class="field-value" style="border-bottom: none; display: inline-block;">{{ $habilitation->validated_n2_at ? \Carbon\Carbon::parse($habilitation->validated_n2_at)->format('d/m/Y') : '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Signature:</span>
            </div>
            @if($habilitation->signature_n2)
                <div style="margin-top: 3px; margin-bottom: 2px;">
                    <img src="{{ $habilitation->signature_n2 }}" alt="Signature N+2" style="max-width: 100%; max-height: 40px;" />
                </div>
            @else
                <div class="signature-area"></div>
            @endif
            @if($habilitation->comment_n2)
            <div style="margin-top: 3px; margin-bottom: 0; font-size: 7.5pt;">
                <strong>Commentaire:</strong> {{ $habilitation->comment_n2 }}
            </div>
            @endif
        </div>

        <!-- Contrôle interne -->
        <div class="validation-column">
            <div class="validation-title">Contrôle interne</div>
            <div class="field-row">
                <span class="field-label">Nom et Prénoms:</span>
            </div>
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%; border-bottom: none;">{{ $habilitation->validatorControl->name ?? '' }}</div>
            <div class="field-row" style="display: flex; align-items: baseline;">
                <span class="field-label" style="margin-right: 5px;">Date:</span>
                <span class="field-value" style="border-bottom: none; display: inline-block;">{{ $habilitation->validated_control_at ? \Carbon\Carbon::parse($habilitation->validated_control_at)->format('d/m/Y') : '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Signature:</span>
            </div>
            @if($habilitation->signature_control)
                <div style="margin-top: 3px; margin-bottom: 2px;">
                    <img src="{{ $habilitation->signature_control }}" alt="Signature Contrôle" style="max-width: 100%; max-height: 40px;" />
                </div>
            @else
                <div class="signature-area"></div>
            @endif
            @if($habilitation->comment_control)
            <div style="margin-top: 3px; margin-bottom: 0; font-size: 7.5pt;">
                <strong>Commentaire:</strong> {{ $habilitation->comment_control }}
            </div>
            @endif
        </div>
    </div>

    <!-- Exécution IT (si applicable) - en dessous des colonnes -->
    @if($habilitation->executor_it)
    <div style="margin-top: 3px; margin-bottom: 0; border: 1px solid #000; padding: 3px;">
        <div class="validation-title" style="margin: -6px -6px 5px -6px;">Exécution IT</div>
        <div class="field-row">
            <span class="field-label">Nom et Prénoms:</span>
            <span class="field-value" style="border-bottom: none;">{{ $habilitation->executorIt->name }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Date:</span>
            <span class="field-value">{{ $habilitation->executed_it_at ? \Carbon\Carbon::parse($habilitation->executed_it_at)->format('d/m/Y') : '' }}</span>
        </div>
        @if($habilitation->comment_it)
        <div style="margin-top: 5px; font-size: 8pt;">
            <strong>Commentaire:</strong> {{ $habilitation->comment_it }}
        </div>
        @endif
    </div>
    @endif

</body>
</html>
