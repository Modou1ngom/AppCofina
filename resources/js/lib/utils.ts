import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return toUrl(urlToCheck) === currentUrl;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/** Affiche le n° de mission (ou un tiret si absent). */
export function formatNumeroMission(numero?: number | string | null): string {
    if (numero === null || numero === undefined || numero === '') {
        return '—';
    }

    return String(numero);
}

/** Formate une date ISO / Y-m-d en jj/mm/aaaa. */
export function formatDateFr(date?: string | null): string {
    if (!date) {
        return '—';
    }

    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return parsed.toLocaleDateString('fr-FR');
}

/** Affiche une période de mission (début – fin). */
export function formatPeriodeMission(debut?: string | null, fin?: string | null): string {
    const d = formatDateFr(debut);
    const f = formatDateFr(fin);

    if (d === '—' && f === '—') {
        return '—';
    }

    return `${d} – ${f}`;
}

/** Formate un montant en style FR (séparateur milliers, 2 décimales). */
export function formatMontant(n?: string | number | null): string {
    if (n === null || n === undefined || n === '') {
        return '—';
    }

    const x = typeof n === 'number' ? n : parseFloat(String(n).replace(/\s/g, '').replace(',', '.'));
    if (Number.isNaN(x)) {
        return String(n);
    }

    return x.toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}
