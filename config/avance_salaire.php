<?php

return [
    /*
    | Intégration RH / écritures : local = tout reste en base AppCofina ;
    | external = envoi HTTP vers une autre application (source de vérité des écritures).
    */
    'integrations' => [
        'mode' => env('AVANCE_SALAIRE_INTEGRATION_MODE', 'local'),
        'external_mirror_local' => filter_var(
            env('AVANCE_SALAIRE_INTEGRATION_EXTERNAL_MIRROR_LOCAL', false),
            FILTER_VALIDATE_BOOL
        ),
        'external' => [
            'base_url' => env('AVANCE_SALAIRE_INTEGRATION_EXTERNAL_BASE_URL', ''),
            'path' => env('AVANCE_SALAIRE_INTEGRATION_EXTERNAL_PATH', '/api/integrations/avances-salaire'),
            'token' => env('AVANCE_SALAIRE_INTEGRATION_EXTERNAL_TOKEN', ''),
            'timeout' => (int) env('AVANCE_SALAIRE_INTEGRATION_EXTERNAL_TIMEOUT', 30),
            'verify_ssl' => filter_var(
                env('AVANCE_SALAIRE_INTEGRATION_EXTERNAL_VERIFY_SSL', true),
                FILTER_VALIDATE_BOOL
            ),
        ],
    ],

    'plafond_pct_defaut' => (float) env('AVANCE_SALAIRE_PLAFOND_PCT', 30),
    'duree_mois_min' => (int) env('AVANCE_SALAIRE_DUREE_MIN', 1),
    'duree_mois_max' => (int) env('AVANCE_SALAIRE_DUREE_MAX', 6),
    'anciennete_mois_min' => (int) env('AVANCE_SALAIRE_ANCIENNETE_MIN', 6),

    /*
     * Barème interne (types d’avance, comptes, durée max, plafonds par catégorie).
     * Clés utilisées côté API et front : salaire | korite | tabaski | rentree
     */
    'types' => [
        'salaire' => [
            'label' => 'Avance sur salaire',
            'compte_charge' => '331200000002',
            'duree_max_mois' => 3,
            'modes_remboursement' => ['par_mois'],
            'mode_remboursement_defaut' => 'par_mois',
            'plafonds' => [
                'non_cadre' => 300_000,
                'cadre' => 500_000,
                'emc' => 1_500_000,
            ],
        ],
        'korite' => [
            'label' => 'Avance Korité',
            'compte_charge' => '331200000005',
            'duree_max_mois' => 10,
            'modes_remboursement' => ['par_mois', 'par_tranche'],
            'mode_remboursement_defaut' => 'par_tranche',
            'plafonds' => [
                'non_cadre' => 300_000,
                'cadre' => 500_000,
                'emc' => 1_500_000,
            ],
        ],
        'tabaski' => [
            'label' => 'Avance Tabaski',
            'compte_charge' => '331200000004',
            'duree_max_mois' => 10,
            'modes_remboursement' => ['par_mois', 'par_tranche'],
            'mode_remboursement_defaut' => 'par_tranche',
            'plafonds' => [
                'non_cadre' => 300_000,
                'cadre' => 500_000,
                'emc' => 1_500_000,
            ],
        ],
        'rentree' => [
            'label' => 'Avance Rentrée scolaire',
            'compte_charge' => '331200000006',
            'duree_max_mois' => 10,
            'modes_remboursement' => ['par_mois', 'par_tranche'],
            'mode_remboursement_defaut' => 'par_tranche',
            'plafonds' => [
                'non_cadre' => 300_000,
                'cadre' => 500_000,
                'emc' => 1_500_000,
            ],
        ],
    ],
];
