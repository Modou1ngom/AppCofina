<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seuil d’alerte encours / fonds propres (suivi signature)
    |--------------------------------------------------------------------------
    |
    | Taux (%) = (encours total / fonds propres) × 100.
    | Encours total = encours propre SI + somme des encours des personnes liées.
    | Plafond réglementaire = fonds propres × (seuil / 100).
    | Écart = plafond réglementaire − encours total.
    |
    | Statut tableau de bord :
    |   - Conforme     : ratio < zone d’alerte
    |   - Alerte       : ratio entre zone d’alerte et seuil (inclus)
    |   - Dépassement  : ratio > seuil → notification, blocage des nouvelles liaisons,
    |                    et enregistrement dans sig_staff_encours_conformite_events
    |
    */
    'encours_taux_seuil_pct' => (float) env('SIG_ENCOURS_TAUX_SEUIL', 10),

    /** Début de la zone d’alerte (ex. 8 %) jusqu’au seuil inclus. */
    'encours_taux_alerte_pct' => (float) env('SIG_ENCOURS_TAUX_ALERTE', 8),

    /*
    |--------------------------------------------------------------------------
    | Types de relation (personnes liées)
    |--------------------------------------------------------------------------
    */
    'types_relation' => [
        'Époux',
        'Épouse',
        'Père',
        'Mère',
        'Fils',
        'Fille',
        'Beau-père',
        'Belle-mère',
        'Beau-fils',
        'Belle-fille',
        'Associé',
        'Société de personne associé',
        'Personne morale contrôlée individuellement ou collectivement',
        'Personne morale contrôlée individuellement ou collectivement par le conjoint',
        'Personne morale contrôlée individuellement ou collectivement par le père, mère, fils, fille',
        'Détention de 10 % des droits de vote ou action',
    ],

];
