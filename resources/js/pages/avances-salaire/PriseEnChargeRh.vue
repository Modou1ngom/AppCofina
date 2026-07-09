<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { ClipboardCheck, Send } from 'lucide-vue-next';

interface Row {
    id: number;
    user_id: number;
    matricule: string;
    nom: string;
    prenom: string;
    montant: number;
    statut: string;
    statut_label?: string;
    eligible: boolean;
    created_at: string | null;
    statut_rh: string | null;
    numero_compte: string | null;
    categorie_staff: string;
    categorie_staff_label: string;
}

interface Props {
    integrationExterne?: boolean;
    demandes: { data: Row[] };
    integrationTemplateLines: Array<{
        numero: number;
        no_batch: string;
        no_compte: string;
        sens: string;
        montant: number;
        code_operation: string | null;
        date_de_valeur: string | null;
        code_agence: string | null;
        libelle_ecriture: string | null;
        user_id: number | null;
        annee_compte: number | null;
        mois_compte: number | null;
    }>;
}

const props = withDefaults(defineProps<Props>(), {
    integrationExterne: false,
    integrationTemplateLines: () => [],
});
const page = usePage();
const authUserId = (page.props.auth as { user?: { id: number } } | undefined)?.user?.id as number | undefined;

const statutLabel = (s: string) =>
    ({
        en_attente_prise_en_charge: 'En attente d’intégration',
        en_cours_traitement: 'En cours d’intégration',
        terminee: 'Terminée',
    } as Record<string, string>)[s] || s;

const libelleStatut = (d: Row) => d.statut_label ?? statutLabel(d.statut);

const peutAgirSurLigne = (d: Row) => authUserId === undefined || d.user_id !== authUserId;

const demarrerTraitement = (d: Row) => {
    if (!peutAgirSurLigne(d) || !['en_attente_prise_en_charge', 'en_cours_traitement'].includes(d.statut)) return;
    router.get(`/avances-salaire/${d.id}/integration-rh/form`);
};

const terminerTraitement = (d: Row) => {
    if (!peutAgirSurLigne(d) || d.statut !== 'en_cours_traitement') return;
    if (!confirm('Terminer l’intégration pour cette demande ?')) return;
    router.post(`/avances-salaire/${d.id}/terminer-integration-rh`, {}, { preserveScroll: true });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: 'Intégration', href: '#' },
];

function formatMontantTemplate(m: number): string {
    return Number(m).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDateValeur(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso + (iso.length === 10 ? 'T12:00:00' : ''));
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('fr-FR');
}

function formatMoisComptable(m: number | null | undefined): string {
    if (m === null || m === undefined) return '—';
    return String(m).padStart(2, '0');
}

function cellOrDash(v: string | number | null | undefined): string {
    if (v === null || v === undefined || v === '') return '—';
    return String(v);
}

function sensLabel(sens: string): string {
    return sens === 'credite' ? 'Crédit' : sens === 'debute' ? 'Débit' : sens;
}

function sensBadgeClass(sens: string): string {
    if (sens === 'credite') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (sens === 'debute') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    return 'border-neutral-200 bg-neutral-50 text-neutral-700';
}

function envoyerTemplateVersApi(): void {
    if (!props.integrationExterne || !props.integrationTemplateLines?.length) return;
    if (!confirm('Envoyer toutes les lignes du tableau vers l’application d’intégration (API) ?')) return;
    router.post('/avances-salaire/integration-rh/envoyer-template-externe', {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Intégration — avances sur salaire" />

        <div class="p-6">
       
            <div class="mt-8 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-neutral-200 bg-gradient-to-r from-red-50 via-white to-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">
                        {{ props.integrationExterne ? 'Historique local (miroir)' : 'Template d’intégration (écritures à transmettre)' }}
                        </h2>
                        <p class="mt-1 text-xs text-neutral-500">
                            {{ props.integrationTemplateLines.length }} ligne(s) d’écriture générée(s)
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="props.integrationExterne && props.integrationTemplateLines?.length"
                            type="button"
                            variant="default"
                            class="gap-2 bg-red-700 hover:bg-red-800"
                            @click="envoyerTemplateVersApi"
                        >
                            <Send class="h-4 w-4" />
                            Envoyer vers l’API
                        </Button>
                        <span class="inline-flex w-fit items-center rounded-full border border-red-100 bg-white px-3 py-1 text-xs font-medium text-red-700 shadow-sm">
                            Template comptable
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1180px] border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">N°</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Batch</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Compte</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Sens</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-neutral-600">Montant</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Opération</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Date valeur</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Agence</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Libellé</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Utilisateur</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Année</th>
                                <th class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-neutral-600">Mois</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr
                                v-for="(l, i) in props.integrationTemplateLines"
                                :key="`${l.no_batch}-${l.numero}-${i}`"
                                class="group transition-colors odd:bg-white even:bg-neutral-50/60 hover:bg-red-50/60"
                            >
                                <td class="sticky left-0 z-10 border-b border-neutral-100 bg-inherit px-4 py-3 font-mono text-xs font-semibold text-neutral-900 group-hover:bg-red-50/60">
                                    {{ l.numero }}
                                </td>
                                <td class="border-b border-neutral-100 px-4 py-3">
                                    <span class="inline-flex rounded-md bg-neutral-900 px-2 py-1 font-mono text-[11px] font-semibold tracking-wide text-white">
                                        {{ l.no_batch }}
                                    </span>
                                </td>
                                <td class="border-b border-neutral-100 px-4 py-3 font-mono text-xs text-neutral-900">{{ l.no_compte }}</td>
                                <td class="border-b border-neutral-100 px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold',
                                            sensBadgeClass(l.sens),
                                        ]"
                                    >
                                        {{ sensLabel(l.sens) }}
                                    </span>
                                </td>
                                <td class="border-b border-neutral-100 px-4 py-3 text-right font-mono text-xs font-semibold tabular-nums text-neutral-950">
                                    {{ formatMontantTemplate(l.montant) }} <span class="font-sans text-[11px] font-medium text-neutral-500">FCFA</span>
                                </td>
                                <td class="border-b border-neutral-100 px-4 py-3 font-mono text-xs text-neutral-700">{{ cellOrDash(l.code_operation) }}</td>
                                <td class="whitespace-nowrap border-b border-neutral-100 px-4 py-3 font-mono text-xs text-neutral-700">{{ formatDateValeur(l.date_de_valeur) }}</td>
                                <td class="border-b border-neutral-100 px-4 py-3">
                                    <span class="inline-flex rounded-md border border-neutral-200 bg-white px-2 py-1 font-mono text-xs text-neutral-800 shadow-sm">
                                        {{ cellOrDash(l.code_agence) }}
                                    </span>
                                </td>
                                <td class="max-w-[260px] truncate border-b border-neutral-100 px-4 py-3 text-xs font-medium text-neutral-800" :title="cellOrDash(l.libelle_ecriture)">
                                    {{ cellOrDash(l.libelle_ecriture) }}
                                </td>
                                <td class="border-b border-neutral-100 px-4 py-3 font-mono text-xs text-neutral-700">{{ l.user_id ?? '—' }}</td>
                                <td class="border-b border-neutral-100 px-4 py-3 font-mono text-xs text-neutral-700">{{ l.annee_compte ?? '—' }}</td>
                                <td class="border-b border-neutral-100 px-4 py-3 font-mono text-xs text-neutral-700">{{ formatMoisComptable(l.mois_compte) }}</td>
                            </tr>
                            <tr v-if="!props.integrationTemplateLines?.length">
                                <td colspan="12" class="px-4 py-14 text-center">
                                    <div class="mx-auto flex max-w-md flex-col items-center rounded-2xl border border-dashed border-neutral-300 bg-neutral-50 px-6 py-8">
                                        <div class="mb-3 rounded-full bg-white p-3 text-neutral-500 shadow-sm">
                                            <ClipboardCheck class="h-6 w-6" />
                                        </div>
                                        <p class="text-sm font-semibold text-neutral-900">Aucune écriture générée</p>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            <template v-if="props.integrationExterne">
                                                Aucune ligne en miroir : les écritures sont traitées sur l’application d’intégration externe.
                                            </template>
                                            <template v-else>
                                                Démarrez une intégration pour créer les lignes du template.
                                            </template>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
