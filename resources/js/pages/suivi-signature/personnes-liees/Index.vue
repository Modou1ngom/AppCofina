<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Code, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Row {
    id: number;
    numero_client: string | null;
    est_personne_morale: boolean;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    encours_credit: string;
}

interface Props {
    personnesLiees: {
        data: Row[];
        meta?: { current_page?: number; total?: number; per_page?: number };
        total?: number;
        current_page?: number;
        per_page?: number;
    };
    filters: { search: string };
}

const props = defineProps<Props>();

const searchLocal = ref(props.filters.search || '');

const currentPage = computed(() => props.personnesLiees.current_page || props.personnesLiees.meta?.current_page || 1);
const totalItems = computed(() => props.personnesLiees.total || props.personnesLiees.meta?.total || 0);
const perPage = computed(() => props.personnesLiees.per_page || props.personnesLiees.meta?.per_page || 10);

const handlePageChange = (page: number) => {
    const p = new URLSearchParams(window.location.search);
    p.set('page', String(page));
    p.set('per_page', String(perPage.value));
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/personnes-liees?${p.toString()}`, {}, { preserveScroll: true, only: ['personnesLiees', 'filters'] });
};

const handleItemsPerPageChange = (items: number) => {
    const p = new URLSearchParams(window.location.search);
    p.set('per_page', String(items));
    p.set('page', '1');
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/personnes-liees?${p.toString()}`, {}, { preserveScroll: true, only: ['personnesLiees', 'filters'] });
};

const applySearch = () => {
    const p = new URLSearchParams();
    p.set('page', '1');
    p.set('per_page', String(perPage.value));
    if (searchLocal.value) p.set('search', searchLocal.value);
    router.get(`/suivi-signature/personnes-liees?${p.toString()}`, {}, { only: ['personnesLiees', 'filters'] });
};

const libelle = (r: Row) => {
    if (r.est_personne_morale && r.raison_sociale) return r.raison_sociale;
    return `${r.prenom ?? ''} ${r.nom ?? ''}`.trim() || `#${r.id}`;
};

const columns: Column[] = [
    { key: 'numero_client', title: 'N° client' },
    { key: 'libelle', title: 'Identité / Raison sociale', sortable: true },
    { key: 'nature', title: 'Nature' },
    { key: 'encours_credit', title: 'Encours' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() =>
    (props.personnesLiees.data || []).map((r) => ({
        ...r,
        numero_client: r.numero_client || '—',
        libelle: libelle(r),
        nature: r.est_personne_morale ? 'Personne morale' : 'Personne physique',
    })),
);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Personnes liées — suivi signature', href: '#' }];

const deleteRow = (id: number) => {
    if (confirm('Supprimer cette fiche ? Les liens avec les staffs seront supprimés.')) {
        router.delete(`/suivi-signature/personnes-liees/${id}`);
    }
};
</script>

<template>
    <Head title="Personnes liées — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Personnes liées</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <Link href="/suivi-signature/personnes-liees/create">
                    <Button>Nouvelle fiche</Button>
                </Link>
            </div>

            <div class="flex max-w-md gap-2">
                <input
                    v-model="searchLocal"
                    type="search"
                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm focus-visible:ring-2 focus-visible:outline-none"
                    placeholder="Rechercher…"
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
                <template #item.numero_client="{ item }">
                    <span class="font-mono text-xs text-gray-700">{{ item.numero_client }}</span>
                </template>
                <template #item.libelle="{ item }">
                    <span class="font-medium text-gray-900">{{ item.libelle }}</span>
                </template>
                <template #item.encours_credit="{ item }">
                    {{ Number(item.encours_credit).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) }}
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/suivi-signature/personnes-liees/${item.id}`"
                            class="inline-flex rounded-md p-2 text-gray-600 hover:bg-gray-100"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/suivi-signature/personnes-liees/${item.id}/edit`"
                            class="inline-flex rounded-md p-2 text-gray-600 hover:bg-gray-100"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex rounded-md p-2 text-red-600 hover:bg-red-50"
                            @click="deleteRow(item.id)"
                        >
                            <Trash2 class="h-5 w-5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
