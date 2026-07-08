export interface MissionSitesCatalog {
    national: string[];
    international: string[];
}

export const MISSION_SITES_CATALOG: MissionSitesCatalog = {
    national: [
        'Dakar',
        'Diourbel',
        'Fatick',
        'Kaffrine',
        'Kaolack',
        'Kédougou',
        'Kolda',
        'Louga',
        'Matam',
        'Saint-Louis',
        'Sédhiou',
        'Tambacounda',
        'Thiès',
        'Ziguinchor',
    ],
    international: [
        'Burkina Faso',
        'Congo',
        "Côte d'Ivoire",
        'France',
        'Gabon',
        'Guinée',
        'Mali',
        'Togo',
    ],
};

export const tousLesSitesMission = (): string[] => [
    ...MISSION_SITES_CATALOG.national,
    ...MISSION_SITES_CATALOG.international,
];
