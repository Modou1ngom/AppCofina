<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Code, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface SigStaffRow {
    id: number;
    reference: string;
    prenom: string;
    nom: string;
    type_personne: string;
    statut: string;
    encours_credit_individuel: string;
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
    router.get(`/suivi-signature/staff?${p.toString()}`, {}, { preserveScroll: true, only: ['staff', 'filters'] });
};

const handleItemsPerPageChange = (items: number) => {
    const p = new URLSearchParams(window.location.search);
    p.set('per_page', String(items));
    p.set('page', '1');
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/staff?${p.toString()}`, {}, { preserveScroll: true, only: ['staff', 'filters'] });
};

const applySearch = () => {
    const p = new URLSearchParams();
    p.set('page', '1');
    p.set('per_page', String(perPage.value));
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/staff?${p.toString()}`, {}, { only: ['staff', 'filters'] });
};

const typeLabel = (t: string) =>
    ({ staff: 'Staff', administrateur: 'Administrateur', apparente_ou_liee: 'Apparentée / liée' } as Record<string, string>)[t] || t;

const columns: Column[] = [
    { key: 'reference', title: 'Référence', sortable: true },
    { key: 'nom_complet', title: 'Nom', sortable: true },
    { key: 'type_personne', title: 'Type' },
    { key: 'statut', title: 'Statut' },
    { key: 'encours_credit_individuel', title: 'Encours total' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    const rows = props.staff.data || [];
    return rows.map((s) => ({
        ...s,
        nom_complet: `${s.prenom} ${s.nom}`,
    }));
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Suivi signature — Staff', href: '#' }];

const deleteStaff = (id: number) => {
    if (confirm('Supprimer cette fiche staff ?')) {
        router.delete(`/suivi-signature/staff/${id}`);
    }
};
</script>

<template>
    <Head title="Staff — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Staff &amp; CA (suivi signature)</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/suivi-signature/staff/create">
                        <Button>Nouvelle fiche (SI)</Button>
                    </Link>
                    <Link v-if="canSaisieMembreCa" href="/suivi-signature/staff/manuel/create">
                        <Button variant="secondary">Membre CA (conformité)</Button>
                    </Link>
                </div>
            </div>

            <div class="flex max-w-md gap-2">
                <input
                    id="search-staff"
                    v-model="searchLocal"
                    type="search"
                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm focus-visible:ring-2 focus-visible:outline-none"
                    placeholder="Rechercher (réf., nom…)"
                    @keydown.enter.prevent="applySearch"
                />
                <Button type="button" variant="secondary" @click="applySearch">Filtrer</Button>
            </div>

            <DataTable
                :headers="columns"
                :items="tableData"
                :current-page="currentPage"
                :items-per-page="perPage"
                :total-items="totalItems"
                show-select
                @page-change="handlePageChange"
                @items-per-page-change="handleItemsPerPageChange"
            >
                <template #item.reference="{ item }">
                    <span class="font-mono text-sm font-medium">{{ item.reference }}</span>
                </template>
                <template #item.nom_complet="{ item }">
                    <span class="text-gray-900">{{ item.nom_complet }}</span>
                </template>
                <template #item.type_personne="{ item }">
                    {{ typeLabel(item.type_personne) }}
                </template>
                <template #item.statut="{ item }">
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            item.statut === 'actif'
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                : 'bg-gray-100 text-gray-800',
                        ]"
                    >
                        {{ item.statut === 'actif' ? 'Actif' : 'Inactif' }}
                    </span>
                </template>
                <template #item.encours_credit_individuel="{ item }">
                    {{ Number(item.encours_credit_individuel).toLocaleString('fr-FR', { minimumFractionDigits: 0 }) }}
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/suivi-signature/staff/${item.id}`"
                            class="inline-flex rounded-md p-2 text-gray-600 hover:bg-gray-100"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/suivi-signature/staff/${item.id}/edit`"
                            class="inline-flex rounded-md p-2 text-gray-600 hover:bg-gray-100"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex rounded-md p-2 text-red-600 hover:bg-red-50"
                            title="Supprimer"
                            @click="deleteStaff(item.id)"
                        >
                            <Trash2 class="h-5 w-5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
