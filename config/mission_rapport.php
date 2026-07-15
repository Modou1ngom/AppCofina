<?php

/**
 * Rubriques optionnelles du rapport de mission (« Questions supplémentaires »).
 * Affichées uniquement si le missionnaire coche la case correspondante.
 */
return [
    'sections' => [
        [
            'cle' => 'rappel_contexte',
            'libelle' => 'Rappel du contexte et des objectifs',
            'description' => 'Quel était l\'objet de la mission ? Quels objectifs vous avaient été fixés au départ ?',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 3,
        ],
        [
            'cle' => 'activites_realisees',
            'libelle' => 'Activités réalisées',
            'description' => 'Quelles actions avez-vous menées, jour par jour ou par étape ? Décrivez les principales activités sur le terrain.',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 5,
        ],
        [
            'cle' => 'detail_par_site',
            'libelle' => 'Détail par site visité',
            'description' => 'Pour chaque site (région ou pays), qu\'avez-vous constaté ou accompli ? Mentionnez les écarts éventuels par rapport au motif indiqué dans la demande.',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 4,
        ],
        [
            'cle' => 'personnes_rencontrees',
            'libelle' => 'Personnes et structures rencontrées',
            'description' => 'Quels interlocuteurs, agences, clients ou partenaires avez-vous rencontrés ? Indiquez les fonctions ou structures concernées.',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 3,
        ],
        [
            'cle' => 'resultats_obtenus',
            'libelle' => 'Résultats obtenus et livrables',
            'description' => 'Quels objectifs ont été atteints ? Quels documents, décisions ou accords en sont issus ?',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 4,
        ],
        [
            'cle' => 'ecarts_planning',
            'libelle' => 'Écarts par rapport au planning initial',
            'description' => 'Y a-t-il eu des retards, changements de programme ou de périmètre ?',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 3,
        ],
        [
            'cle' => 'difficultes',
            'libelle' => 'Difficultés rencontrées',
            'description' => 'Quels obstacles opérationnels, contraintes logistiques ou blocages avez-vous rencontrés ?',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 3,
        ],
        [
            'cle' => 'risques_incidents',
            'libelle' => 'Risques ou incidents signalés',
            'description' => 'Des faits de sécurité, de conformité ou de réputation doivent-ils être signalés ?',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 3,
        ],
        [
            'cle' => 'recommandations',
            'libelle' => 'Recommandations et suites à donner',
            'description' => 'Quelles actions de suivi proposez-vous au retour de mission ? Qui doit intervenir et dans quel délai ?',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 4,
        ],
        [
            'cle' => 'conclusion',
            'libelle' => 'Conclusion générale',
            'description' => 'Comment résumez-vous globalement le déroulement et l\'issue de la mission ?',
            'obligatoire' => false,
            'min_length' => 0,
            'rows' => 3,
        ],
    ],
];
