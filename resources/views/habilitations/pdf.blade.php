<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche d'habilitations #{{ $habilitation->id }}</title>
    <style>
        @page {
            margin-top: 0.3cm;
            margin-bottom: 0.2cm;
            margin-left: 1cm;
            margin-right: 1cm;
            size: A4;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 9pt;
            line-height: 1.15;
            color: #000;
        }
        .header-container {
            display: table;
            width: 100%;
            margin-top: 15px;
            margin-bottom: 10px;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
        }
        .header-left {
            display: table-cell;
            width: 25%;
            vertical-align: top;
        }
        .header-center {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: middle;
        }
        .header-right {
            display: table-cell;
            width: 25%;
            text-align: right;
            vertical-align: top;
            font-size: 8pt;
        }
        .logo {
            font-size: 24pt;
            font-weight: bold;
            color: #000;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        .main-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 2px 0;
            background-color: #DC143C;
            color: white;
            padding: 4px;
        }
        .subtitle {
            font-size: 10pt;
            font-weight: bold;
            margin-top: 2px;
        }
        .date-info {
            margin-bottom: 5px;
            margin-left: 10px;
        }
        .two-columns {
            display: table;
            width: 100%;
            margin-top: 15px;
            margin-bottom: 8px;
            border-collapse: separate;
            border-spacing: 8px 0;
        }
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .section-header {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #DC143C;
            color: white;
            padding: 2.5px;
            margin-bottom: 6px;
            margin-top: 15px;
            text-align: center;
        }
        .field-row {
            margin-bottom: 4px;
            min-height: 13px;
        }
        .field-label {
            font-weight: bold;
            display: inline-block;
            min-width: 95px;
            font-size: 8.5pt;
        }
        .field-value {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 150px;
            padding: 0 2px;
            font-size: 8.5pt;
        }
        .checkbox-container {
            margin: 5px 0;
        }
        .checkbox-row {
            margin: 1.5px 0;
            display: inline-block;
            width: 48%;
            vertical-align: top;
        }
        .checkbox-box {
            display: inline-block;
            width: 9px;
            height: 9px;
            border: 1.5px solid #000;
            margin-right: 3px;
            vertical-align: middle;
            position: relative;
        }
        .checkbox-box.checked::before {
            content: "✓";
            position: absolute;
            top: -3px;
            left: -1px;
            font-size: 10px;
            font-weight: bold;
            line-height: 9px;
        }
        .checkbox-text {
            display: inline-block;
            vertical-align: middle;
            font-size: 8.5pt;
        }
        .checkbox-text-small {
            font-size: 7.5pt;
        }
        .note-text {
            font-size: 7pt;
            font-style: italic;
            margin-top: 3px;
            color: #333;
        }
        .text-box {
            border: 1px solid #000;
            min-height: 70px;
            padding: 3px;
            margin-top: 4px;
            margin-bottom: 8px;
            font-size: 8.5pt;
        }
        .validation-container {
            margin-top: 15px;
        }
        .validation-columns {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 15px;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .validation-column {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            border: 1px solid #000;
            padding: 6px 6px 8px 6px;
            border-right: none;
        }
        .validation-column:last-child {
            border-right: 1px solid #000;
        }
        .validation-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
            text-align: center;
            background-color: #DC143C;
            color: white;
            padding: 3px;
            margin: -4px -4px 4px -4px;
        }
        .signature-area {
            border-bottom: 1px solid #000;
            margin-top: 25px;
            min-height: 25px;
            width: 100%;
            margin-bottom: 0;
        }
        .signature-label {
            text-align: center;
            font-size: 7pt;
            margin-top: 2px;
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
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header-container">
        <div class="header-left">
            <div class="logo">cofina</div>
        </div>
        <div class="header-center">
            <div class="main-title">DEMANDE D'HABILITATIONS</div>
            <div class="subtitle">Fiche d'habilitations</div>
        </div>
        <div class="header-right">
            <div class="date-info"><strong>Date de création:</strong> {{ $habilitation->created_at->format('d/m/y') }}</div>
            <div><strong>Filiale</strong> {{ $habilitation->subsidiary ?? '' }}</div>
        </div>
    </div>

    <!-- DEMANDEUR et BÉNÉFICIAIRE -->
    <div class="two-columns">
        <!-- Colonne gauche: DEMANDEUR -->
        <div class="column">
            <div class="section-header">DEMANDEUR</div>
            <div class="field-row">
                <span class="field-label">Direction / Département / Service:</span>
                <span class="field-value">{{ $habilitation->requester_direction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Nom & Prénom:</span>
                <span class="field-value">{{ $habilitation->requester->prenom }} {{ $habilitation->requester->nom }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Fonction:</span>
                <span class="field-value">{{ $habilitation->requester->fonction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">E-mail:</span>
                <span class="field-value">{{ $habilitation->requester_email ?? $habilitation->requester->email ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Téléphone:</span>
                <span class="field-value">{{ $habilitation->requester_telephone ?? $habilitation->requester->telephone ?? '' }}</span>
            </div>
        </div>

        <!-- Colonne droite: BÉNÉFICIAIRE -->
        <div class="column">
            <div class="section-header">BÉNÉFICIAIRE</div>
            <div class="field-row">
                <span class="field-label">Direction / Département / Service:</span>
                <span class="field-value">{{ $habilitation->beneficiary_direction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Nom & Prénom:</span>
                <span class="field-value">{{ $habilitation->beneficiary->prenom }} {{ $habilitation->beneficiary->nom }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Fonction:</span>
                <span class="field-value">{{ $habilitation->beneficiary->fonction ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">E-mail:</span>
                <span class="field-value">{{ $habilitation->beneficiary_email ?? $habilitation->beneficiary->email ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Site:</span>
                <span class="field-value">{{ $habilitation->beneficiary_site ?? '' }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Téléphone:</span>
                <span class="field-value">{{ $habilitation->beneficiary_telephone ?? $habilitation->beneficiary->telephone ?? '' }}</span>
            </div>
        </div>
    </div>

    <!-- DÉTAILS DE LA DEMANDE -->
    <div class="section-header" style="margin-top: 15px;">DÉTAILS DE LA DEMANDE</div>

    <!-- Type de la demande -->
    <div style="margin: 6px 0; padding-left: 15px;">
        <strong style="font-size: 9pt;">Type de la demande:</strong>
        <div style="margin-left: 15px; margin-top: 5px;">
            <span class="checkbox-box checked"></span>
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
    <div style="margin: 8px 0; padding-left: 15px;">
        <strong style="font-size: 9pt;">Type d'application ou service:</strong>
        <div class="checkbox-container" style="margin-left: 15px; margin-top: 10px; margin-bottom: 10px;">
            @if($habilitation->applications && count($habilitation->applications) > 0)
                @foreach($habilitation->applications as $app)
                <div class="checkbox-row" style="margin-left: 0;">
                    <span class="checkbox-box checked"></span>
                    <span class="checkbox-text">{{ $app }}</span>
                </div>
                @endforeach
            @endif
            @if($habilitation->other_application)
            <div class="checkbox-row" style="margin-left: 0;">
                <span class="checkbox-box checked"></span>
                <span class="checkbox-text">Autres</span>
                <span class="other-app-text">{{ $habilitation->other_application }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Autres champs -->
    <div style="padding-left: 15px; margin-top: 8px;">
        <div class="field-row">
            <span class="field-label">Autre (préciser):</span>
            <span class="field-value">{{ $habilitation->other_application ?? '' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Profil Actuel (préciser):</span>
            <span class="field-value">{{ $habilitation->current_profile ?? '' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Profil demandé (préciser):</span>
            <span class="field-value">{{ $habilitation->requested_profile ?? '' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Date d'implémentation souhaitée:</span>
            <span class="field-value">{{ $habilitation->desired_implementation_date ? \Carbon\Carbon::parse($habilitation->desired_implementation_date)->format('d/m/Y') : '' }}</span>
        </div>
    </div>

    <!-- Type de profil -->
    <div style="margin: 7px 0; padding-left: 15px;">
        <strong style="font-size: 9pt;">Type de profil:</strong>
        <div style="margin-left: 15px; margin-top: 5px;">
            @if($habilitation->profile_type)
            <div>
                <span class="checkbox-box checked"></span>
                <span class="checkbox-text">{{ $habilitation->profile_type }}</span>
            </div>
            @if($habilitation->specific_profile)
            <div style="margin-left: 20px; margin-top: 3px; font-size: 8.5pt;">{{ $habilitation->specific_profile }}</div>
            @endif
            @endif
        </div>
    </div>

    <!-- Période de validité -->
    <div style="margin: 7px 0; padding-left: 15px;">
        <strong style="font-size: 9pt;">Période de validité:</strong>
        <div style="margin-left: 15px; margin-top: 5px;">
            @if($habilitation->validity_period === 'Permanent')
            <div>
                <span class="checkbox-box checked"></span>
                <span class="checkbox-text">Permanent</span>
            </div>
            @elseif($habilitation->validity_period === 'Temporaire')
            <div>
                <span class="checkbox-box checked"></span>
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

    <div class="note-text" style="margin-bottom: 8px;">
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
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%;">{{ $habilitation->validatorN1->name ?? '' }}</div>
            <div class="field-row">
                <span class="field-label">Date:</span>
            </div>
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%;">{{ $habilitation->validated_n1_at ? \Carbon\Carbon::parse($habilitation->validated_n1_at)->format('d/m/Y') : '' }}</div>
            <div class="field-row">
                <span class="field-label">Signature:</span>
            </div>
            <div class="signature-area"></div>
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
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%;">{{ $habilitation->validatorN2->name ?? '' }}</div>
            <div class="field-row">
                <span class="field-label">Date:</span>
            </div>
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%;">{{ $habilitation->validated_n2_at ? \Carbon\Carbon::parse($habilitation->validated_n2_at)->format('d/m/Y') : '' }}</div>
            <div class="field-row">
                <span class="field-label">Signature:</span>
            </div>
            <div class="signature-area"></div>
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
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%;">{{ $habilitation->validatorControl->name ?? '' }}</div>
            <div class="field-row">
                <span class="field-label">Date:</span>
            </div>
            <div class="field-value" style="display: block; margin-bottom: 3px; min-width: 100%;">{{ $habilitation->validated_control_at ? \Carbon\Carbon::parse($habilitation->validated_control_at)->format('d/m/Y') : '' }}</div>
            <div class="field-row">
                <span class="field-label">Signature:</span>
            </div>
            <div class="signature-area"></div>
            @if($habilitation->comment_control)
            <div style="margin-top: 3px; margin-bottom: 0; font-size: 7.5pt;">
                <strong>Commentaire:</strong> {{ $habilitation->comment_control }}
            </div>
            @endif
        </div>
    </div>

    <!-- Exécution IT (si applicable) - en dessous des colonnes -->
    @if($habilitation->executor_it)
    <div style="margin-top: 6px; margin-bottom: 0; border: 1px solid #000; padding: 5px;">
        <div class="validation-title" style="margin: -6px -6px 5px -6px;">Exécution IT</div>
        <div class="field-row">
            <span class="field-label">Nom et Prénoms:</span>
            <span class="field-value">{{ $habilitation->executorIt->name }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Date:</span>
            <span class="field-value">{{ $habilitation->executed_it_at ? \Carbon\Carbon::parse($habilitation->executed_it_at)->format('d/m/Y') : '' }}</span>
        </div>
        <div class="signature-area"></div>
        <div class="signature-label">Signature</div>
        @if($habilitation->comment_it)
        <div style="margin-top: 5px; font-size: 8pt;">
            <strong>Commentaire:</strong> {{ $habilitation->comment_it }}
        </div>
        @endif
    </div>
    @endif

</body>
</html>
