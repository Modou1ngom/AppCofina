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
