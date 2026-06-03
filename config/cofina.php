<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compte utilisateur créé avec le profil RH
    |--------------------------------------------------------------------------
    |
    | Lorsqu'un profil est créé avec une adresse e-mail, un compte User est
    | provisionné automatiquement (mot de passe par défaut, changement obligatoire).
    |
    */
    'provision_user_on_profil_create' => env('COFINA_PROVISION_USER_ON_PROFIL', true),

    'default_user_password' => env('COFINA_DEFAULT_USER_PASSWORD', 'Cofina@2025'),

    /*
    |--------------------------------------------------------------------------
    | E-mails collaborateurs (import Excel)
    |--------------------------------------------------------------------------
    |
    | Si le fichier ne contient pas de colonne avec « @ », l'import peut générer
    | prenom.nom@domaine à partir du nom/prénom, ou compléter un login AD.
    |
    */
    'email_domain' => env('COFINA_EMAIL_DOMAIN', 'cofinacorp.com'),

    'import' => [
        // false = uniquement les e-mails présents dans le fichier Excel (recommandé)
        'generate_email_from_name' => env('COFINA_IMPORT_GENERATE_EMAIL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rôles utilisateur selon le département (import / provisioning)
    |--------------------------------------------------------------------------
    |
    | Clé = nom de département normalisé (ex. IT, RH). Valeur = slug du rôle.
    | Tout autre département reçoit default_departement_role (metier).
    |
    */
    'departement_role_map' => [
        'IT' => 'admin',
        'RH' => 'rh',
        'RESSOURCES HUMAINES' => 'rh',
        'CONTROLE' => 'controle',
        'CONFORMITE' => 'conformite',
    ],

    'default_departement_role' => env('COFINA_DEFAULT_DEPARTEMENT_ROLE', 'metier'),

    /*
    |--------------------------------------------------------------------------
    | Compte super administrateur (seeder)
    |--------------------------------------------------------------------------
    */
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Super Admin'),
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@cofina.sn'),
        'password' => env('SUPERADMIN_PASSWORD') ?: env('COFINA_DEFAULT_USER_PASSWORD', 'Cofina@2025'),
        'must_change_password' => env('SUPERADMIN_MUST_CHANGE_PASSWORD', true),
        'reset_password' => env('SUPERADMIN_RESET_PASSWORD', false),
    ],

];
