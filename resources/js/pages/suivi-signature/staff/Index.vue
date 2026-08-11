<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable, { type Column } from '@/components/DataTable.vue';
import {
    AlertTriangle,
    CheckCircle2,
    CircleHelp,
    Network,
    Search,
    ShieldAlert,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type StatutConformite = 'conforme' | 'alerte' | 'depassement' | 'non_evalue';

interface SigStaffRow {
    id: number;
    matricule: string;
    nom_complet: string;
    fonction: string | null;
    fonds_propres: number | null;
    seuil_pct: number;
    plafond_reglementaire: number | null;
    encours_staff_ca: number;
    encours_personnes_liees: number;
    encours_total: number;
    ratio_pct: number | null;
    ecart: number | null;
    statut_conformite: StatutConformite;
    type_personne: string;
    statut: string;
}

interface Synthese {
    nb_fiches: number;
    fonds_propres_reference: number | null;
    seuil_pct: number;
    plafond_reglementaire: number | null;
    encours_staff_ca: number;
    encours_personnes_liees: number;
    encours_total: number;
    ratio_pct: number | null;
    ecart: number | null;
    statut_conformite: StatutConformite;
    nb_conforme: number;
    nb_alerte: number;
    nb_depassement: number;
    nb_non_evalue: number;
}

interface Props {
    staff: {
        data: SigStaffRow[];
        links?: unknown[];
        meta?: { current_page?: number; total?: number; per_page?: number };
        total?: number;
        current_page?: number;
        per_page?: number;
    };
    synthese: Synthese;
    filters: { search: string };
}

const props = defineProps<Props>();

const page = usePage();
const authProps = computed(() => page.props.auth as { isAdmin?: boolean; isConformite?: boolean });
const canSaisieMembreCa = computed(() => !!(authProps.value?.isAdmin || authProps.value?.isConformite));

const searchLocal = ref(props.filters.search || '');

watch(
    () => props.filters.search,
    (v) => {
        searchLocal.value = v || '';
    },
);

const currentPage = computed(() => props.staff.current_page || props.staff.meta?.current_page || 1);
const totalItems = computed(() => props.staff.total || props.staff.meta?.total || 0);
const perPage = computed(() => props.staff.per_page || props.staff.meta?.per_page || 10);

const handlePageChange = (page: number) => {
    const p = new URLSearchParams(window.location.search);
    p.set('page', String(page));
    p.set('per_page', String(perPage.value));
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/staff?${p.toString()}`, {}, { preserveScroll: true, only: ['staff', 'synthese', 'filters'] });
};

const handleItemsPerPageChange = (items: number) => {
    const p = new URLSearchParams(window.location.search);
    p.set('per_page', String(items));
    p.set('page', '1');
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/staff?${p.toString()}`, {}, { preserveScroll: true, only: ['staff', 'synthese', 'filters'] });
};

const applySearch = () => {
    const p = new URLSearchParams();
    p.set('page', '1');
    p.set('per_page', String(perPage.value));
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/staff?${p.toString()}`, {}, { only: ['staff', 'synthese', 'filters'] });
};

function formatMontantFr(n: number | null | undefined): string {
    if (n === null || n === undefined) return '—';
    return n.toLocaleString('fr-FR', { maximumFractionDigits: 0 });
}

function formatPctFr(n: number | null | undefined): string {
    if (n === null || n === undefined) return '—';
    return `${n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %`;
}

function statutConformiteLabel(s: StatutConformite): string {
    if (s === 'conforme') return 'Conforme';
    if (s === 'alerte') return 'Alerte';
    if (s === 'depassement') return 'Dépassement';
    return 'Non évalué';
}

const columns: Column[] = [
    { key: 'matricule', title: 'Matricule', sortable: true },
    { key: 'nom_complet', title: 'Nom & Prénom du Staff', sortable: true },
    { key: 'fonds_propres', title: 'Fonds propres' },
    { key: 'seuil_pct', title: 'Seuil' },
    { key: 'plafond_reglementaire', title: 'Plafond réglementaire' },
    { key: 'encours_staff_ca', title: 'Encours Staff/CA' },
    { key: 'encours_personnes_liees', title: 'Encours personnes liées' },
    { key: 'encours_total', title: 'Encours total' },
    { key: 'ratio_pct', title: 'Ratio' },
    { key: 'ecart', title: 'Écart' },
    { key: 'statut_conformite', title: 'Statut' },
];

const tableData = computed(() => props.staff.data || []);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Suivi signature — Staff', href: '#' }];
</script>

<template>
    <Head title="Staff — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-sidebar-border bg-card shadow-sm">
                        <Network class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Staff &amp; CA</h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Suivi signature — encours, plafond et conformité réglementaire.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/suivi-signature/staff/create?reset=1">
                        <Button>Nouvelle fiche (SI)</Button>
                    </Link>
                    <Link v-if="canSaisieMembreCa" href="/suivi-signature/staff/manuel/create">
                        <Button variant="secondary">Membre CA (conformité)</Button>
                    </Link>
                    <Link v-if="canSaisieMembreCa" href="/suivi-signature/parametrage">
                        <Button variant="outline">Paramétrage</Button>
                    </Link>
                </div>
            </div>

            <div class="flex flex-col gap-3 rounded-lg border border-sidebar-border bg-card p-3 shadow-sm sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input
                        id="search-staff"
                        v-model="searchLocal"
                        type="search"
                        class="h-9 pl-9"
                        placeholder="Rechercher (matricule, nom…)"
                        @keydown.enter.prevent="applySearch"
                    />
                </div>
                <Button type="button" variant="secondary" class="shrink-0" @click="applySearch">Filtrer</Button>
            </div>

            <section class="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sidebar-border bg-muted/40 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Synthèse</h2>
                        <p class="text-muted-foreground text-xs">
                            Totaux sur {{ synthese.nb_fiches }} fiche{{ synthese.nb_fiches > 1 ? 's' : '' }}
                            <span v-if="filters.search"> · filtre actif</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-medium text-emerald-800">
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            {{ synthese.nb_conforme }} conforme{{ synthese.nb_conforme > 1 ? 's' : '' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 font-medium text-orange-800">
                            <AlertTriangle class="h-3.5 w-3.5" />
                            {{ synthese.nb_alerte }} alerte{{ synthese.nb_alerte > 1 ? 's' : '' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 font-medium text-red-800">
                            <ShieldAlert class="h-3.5 w-3.5" />
                            {{ synthese.nb_depassement }} dépassement{{ synthese.nb_depassement > 1 ? 's' : '' }}
                        </span>
                        <span
                            v-if="synthese.nb_non_evalue > 0"
                            class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 font-medium text-gray-700"
                        >
                            <CircleHelp class="h-3.5 w-3.5" />
                            {{ synthese.nb_non_evalue }} non évalué{{ synthese.nb_non_evalue > 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-px bg-sidebar-border sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Encours Staff/CA
                        </p>
                        <p class="mt-2 text-xl font-semibold tabular-nums tracking-tight text-gray-900">
                            {{ formatMontantFr(synthese.encours_staff_ca) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Encours liés
                        </p>
                        <p class="mt-2 text-xl font-semibold tabular-nums tracking-tight text-gray-900">
                            {{ formatMontantFr(synthese.encours_personnes_liees) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-[11px] font-medium tracking-wide text-primary/80 uppercase">Encours total</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums tracking-tight text-primary">
                            {{ formatMontantFr(synthese.encours_total) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Plafond
                        </p>
                        <p class="mt-2 text-xl font-semibold tabular-nums tracking-tight text-gray-900">
                            {{ formatMontantFr(synthese.plafond_reglementaire) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Ratio global
                        </p>
                        <p class="mt-2 text-xl font-semibold tabular-nums tracking-tight text-gray-900">
                            {{ formatPctFr(synthese.ratio_pct) }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-[11px]">
                            Seuil {{ formatPctFr(synthese.seuil_pct) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Écart vs plafond
                        </p>
                        <p
                            class="mt-2 text-xl font-semibold tabular-nums tracking-tight"
                            :class="synthese.ecart != null && synthese.ecart < 0 ? 'text-red-600' : 'text-emerald-700'"
                        >
                            {{ formatMontantFr(synthese.ecart) }}
                        </p>
                        <p
                            v-if="synthese.fonds_propres_reference != null"
                            class="text-muted-foreground mt-1 text-[11px]"
                        >
                            FP réf. {{ formatMontantFr(synthese.fonds_propres_reference) }}
                        </p>
                    </div>
                </div>
            </section>

            <DataTable
                :headers="columns"
                :items="tableData"
                :current-page="currentPage"
                :items-per-page="perPage"
                :total-items="totalItems"
                :show-select="false"
                :selectable="false"
                @page-change="handlePageChange"
                @items-per-page-change="handleItemsPerPageChange"
            >
                <template #item.matricule="{ item }">
                    <span class="font-mono text-xs font-medium whitespace-nowrap text-slate-600">
                        {{ item.matricule }}
                    </span>
                </template>
                <template #item.nom_complet="{ item }">
                    <Link
                        :href="`/suivi-signature/staff/${item.id}`"
                        class="font-medium whitespace-nowrap text-slate-900 underline-offset-2 hover:text-primary hover:underline"
                    >
                        {{ item.nom_complet }}
                    </Link>
                </template>
                <template #item.fonds_propres="{ item }">
                    <span class="tabular-nums whitespace-nowrap text-slate-700">
                        {{ formatMontantFr(item.fonds_propres) }}
                    </span>
                </template>
                <template #item.seuil_pct="{ item }">
                    <span class="tabular-nums whitespace-nowrap text-slate-700">
                        {{ formatPctFr(item.seuil_pct) }}
                    </span>
                </template>
                <template #item.plafond_reglementaire="{ item }">
                    <span class="tabular-nums whitespace-nowrap text-slate-700">
                        {{ formatMontantFr(item.plafond_reglementaire) }}
                    </span>
                </template>
                <template #item.encours_staff_ca="{ item }">
                    <span class="tabular-nums whitespace-nowrap text-slate-700">
                        {{ formatMontantFr(item.encours_staff_ca) }}
                    </span>
                </template>
                <template #item.encours_personnes_liees="{ item }">
                    <span class="tabular-nums whitespace-nowrap text-slate-700">
                        {{ formatMontantFr(item.encours_personnes_liees) }}
                    </span>
                </template>
                <template #item.encours_total="{ item }">
                    <span class="tabular-nums font-semibold whitespace-nowrap text-slate-900">
                        {{ formatMontantFr(item.encours_total) }}
                    </span>
                </template>
                <template #item.ratio_pct="{ item }">
                    <span class="tabular-nums whitespace-nowrap text-slate-700">
                        {{ formatPctFr(item.ratio_pct) }}
                    </span>
                </template>
                <template #item.ecart="{ item }">
                    <span
                        class="tabular-nums whitespace-nowrap"
                        :class="item.ecart != null && item.ecart < 0 ? 'font-medium text-red-600' : 'text-slate-700'"
                    >
                        {{ formatMontantFr(item.ecart) }}
                    </span>
                </template>
                <template #item.statut_conformite="{ item }">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                        :class="{
                            'border-emerald-200 bg-emerald-50 text-emerald-800': item.statut_conformite === 'conforme',
                            'border-orange-200 bg-orange-50 text-orange-800': item.statut_conformite === 'alerte',
                            'border-red-200 bg-red-50 text-red-800': item.statut_conformite === 'depassement',
                            'border-gray-200 bg-gray-50 text-gray-700': item.statut_conformite === 'non_evalue',
                        }"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="{
                                'bg-emerald-500': item.statut_conformite === 'conforme',
                                'bg-orange-500': item.statut_conformite === 'alerte',
                                'bg-red-500': item.statut_conformite === 'depassement',
                                'bg-gray-400': item.statut_conformite === 'non_evalue',
                            }"
                        />
                        {{ statutConformiteLabel(item.statut_conformite) }}
                    </span>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
