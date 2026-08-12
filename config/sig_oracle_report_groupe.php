<?php

/**
 * Suivi signature — données REPORT_GROUPE.
 *
 * Mode recommandé sans oci8 côté PHP : proxy HTTP (service Python) — voir clés SIG_ORACLE_HTTP_*.
 * Mode optionnel : connexion Oracle directe (extension oci8 + ORACLE_REPORT_GROUPE_* + requêtes SQL).
 */

return [
    'enabled' => env('ORACLE_REPORT_GROUPE_LOOKUP_ENABLED', false),

    'lookup_personnel_sql' => env('ORACLE_REPORT_GROUPE_LOOKUP_PERSONNEL_SQL', ''),

    'staff_liees_sql' => env('ORACLE_REPORT_GROUPE_STAFF_LIEES_SQL', ''),

    /*
    | Préfixe des tables Oracle par environnement / filiale.
    | Tables : {CC}_KYC, {CC}_ENCOURS_CLIENT, {CC}_DETECTION_AUTOMATIQUE
    | Clé = nom de filiale normalisé (minuscules, sans accents) ou code ISO.
    */
    'pays' => [
        'default_prefix' => env('ORACLE_PAYS_PREFIX', 'SN'),
        'filiale_prefixes' => [
            // Sénégal
            'senegal' => 'SN',
            'sn' => 'SN',
            // Togo
            'togo' => 'TG',
            'tg' => 'TG',
            // Congo (Brazzaville)
            'congo' => 'CG',
            'congo brazzaville' => 'CG',
            'republique du congo' => 'CG',
            'cg' => 'CG',
            // Côte d'Ivoire
            'cote d\'ivoire' => 'CI',
            'cote divoire' => 'CI',
            'cote-divoire' => 'CI',
            'ivoire' => 'CI',
            'ci' => 'CI',
            // Guinée
            'guinee' => 'GN',
            'guinee conakry' => 'GN',
            'gn' => 'GN',
            // Mali
            'mali' => 'ML',
            'ml' => 'ML',
            // Burkina Faso
            'burkina faso' => 'BF',
            'burkina' => 'BF',
            'bf' => 'BF',
            // Bénin
            'benin' => 'BJ',
            'bj' => 'BJ',
            // Gabon
            'gabon' => 'GA',
            'ga' => 'GA',
            // Cameroun
            'cameroun' => 'CM',
            'cm' => 'CM',
            // RDC (si présent)
            'rdc' => 'CD',
            'congo kinshasa' => 'CD',
            'republique democratique du congo' => 'CD',
            'cd' => 'CD',
        ],
    ],

    /*
    | Proxy HTTP vers un service Python (FastAPI, COFIdash, etc.) qui se connecte à Oracle avec oracledb.
    | Contrat : voir scripts/oracle-proxy-python/app.py
    */
    'http' => [
        'enabled' => env('SIG_ORACLE_HTTP_ENABLED', false),
        'base_url' => rtrim((string) env('SIG_ORACLE_HTTP_BASE_URL', ''), '/'),
        'timeout' => (int) env('SIG_ORACLE_HTTP_TIMEOUT', 30),
        'verify_ssl' => filter_var(env('SIG_ORACLE_HTTP_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'token' => env('SIG_ORACLE_HTTP_TOKEN', ''),
        'lookup_personnel_path' => env('SIG_ORACLE_HTTP_LOOKUP_PERSONNEL_PATH', '/api/sig/lookup-personnel'),
        'staff_liees_path' => env('SIG_ORACLE_HTTP_STAFF_LIEES_PATH', '/api/sig/staff/{matricule}/personnes-liees'),
        'detection_staff_clients_path' => env('SIG_ORACLE_HTTP_DETECTION_STAFF_CLIENTS_PATH', '/api/sig/detection-staff-clients'),
        'alertes_doublons_clients_path' => env('SIG_ORACLE_HTTP_ALERTES_DOUBLONS_CLIENTS_PATH', '/api/sig/alertes-doublons-clients'),
    ],
];
