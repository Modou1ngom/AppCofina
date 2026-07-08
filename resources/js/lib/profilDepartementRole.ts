export function normalizeDepartementKey(departement: string): string {
    let normalized = departement.replace(/informatique/i, 'IT');
    normalized = normalized.replace(/^direction\s+/i, '');
    normalized = normalized.replace(/^departement\s+/i, '');

    if (/exploitation/i.test(normalized)) {
        normalized = 'EXPLOITATION';
    }
    if (/controle\s+permanent|contrôle\s+permanent/i.test(normalized)) {
        normalized = 'CONTROLE PERMANENT';
    }

    const key = normalized.toUpperCase().trim();

    return key
        .replace(/É|È|Ê|Ë/g, 'E')
        .replace(/À|Â/g, 'A')
        .replace(/Ç/g, 'C')
        .replace(/Ô|Ö/g, 'O')
        .replace(/Û|Ü/g, 'U')
        .replace(/Î|Ï/g, 'I')
        .replace(/Ù|Ú/g, 'U');
}

export function resolveDepartementRoleSlug(
    departement: string | null | undefined,
    map: Record<string, string>,
    defaultRole: string,
): string | null {
    if (!departement?.trim()) {
        return null;
    }

    const key = normalizeDepartementKey(departement);

    return map[key] ?? defaultRole;
}
