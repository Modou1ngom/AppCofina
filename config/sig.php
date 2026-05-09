<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seuil d’alerte encours / fonds propres (suivi signature)
    |--------------------------------------------------------------------------
    |
    | Taux (%) = (encours total / fonds propres) × 100.
    | Encours total = encours propre SI + somme des encours des personnes liées.
    | Au-delà du seuil : alerte, notification, blocage des nouvelles liaisons,
    | et enregistrement automatique dans sig_staff_encours_conformite_events
    | (voir rapport de conformité).
    |
    */
    'encours_taux_seuil_pct' => (float) env('SIG_ENCOURS_TAUX_SEUIL', 10),

];
