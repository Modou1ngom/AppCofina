<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed, ref } from 'vue';
import { ArrowLeftRight, Filter, UserMinus, UserPlus } from 'lucide-vue-next';

interface MouvementRow {
    id: number;
    type: string;
    type_label: string;
    date_effet: string | null;
    motif: string | null;
    fonction_avant: string | null;
    fonction_apres: string | null;
    departement_avant: string | null;
    departement_apres: string | null;
    site_avant: string | null;
    site_apres: string | null;
    profil: {
        id: number;
        nom: string;
        prenom: string;
        matricule: string;
        statut: string;
    } | null;
    createur: string | null;
}

interface Props {
    mouvements: {
        data: MouvementRow[];
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
        meta?: { total?: number; current_page?: number; per_page?: number };
    };
    types: { value: string; label: string }[];
    filters: { type: string; search: string };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enrôlement staff', href: '/profils' },
    { title: 'Mouvements RH', href: '/profils/mouvements' },
];

const filters = ref({
    type: props.filters.type || '',
    search: props.filters.search || '',
});

const currentPage = computed(() => props.mouvements.current_page || props.mouvements.meta?.current_page || 1);
const totalItems = computed(() => props.mouvements.total || props.mouvements.meta?.total || 0);
const perPage = computed(() => props.mouvements.per_page || props.mouvements.meta?.per_page || 15);

const applyFilters = () => {
    const params = new URLSearchParams();
    if (filters.value.type) {
        params.set('type', filters.value.type);
    }
    if (filters.value.search) {
        params.set('search', filters.value.search);
    }
    params.set('page', '1');
    router.visit(`/profils/mouvements?${params.toString()}`, { preserveScroll: true });
};

const handlePageChange = (page: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page.toString());
    router.get(`/profils/mouvements?${urlParams.toString()}`, {}, { preserveState: true, preserveScroll: true });
};

const handleItemsPerPageChange = (items: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', items.toString());
    urlParams.set('page', '1');
    router.get(`/profils/mouvements?${urlParams.toString()}`, {}, { preserveState: true, preserveScroll: true });
};

const typeBadge = (type: string) => {
    if (type === 'arrivee') {
        return 'bg-green-100 text-green-800';
    }
    if (type === 'depart') {
        return 'bg-red-100 text-red-800';
    }
    return 'bg-blue-100 text-blue-800';
};

const formatDate = (value: string | null) => {
    if (!value) {
        return '—';
    }
    const [y, m, d] = value.split('-');
    return d && m && y ? `${d}/${m}/${y}` : value;
};

const columns: Column[] = [
    { key: 'date_effet', title: 'DATE' },
    { key: 'collaborateur', title: 'COLLABORATEUR' },
    { key: 'type_label', title: 'MOUVEMENT' },
    { key: 'detail', title: 'DÉTAIL' },
    { key: 'motif', title: 'MOTIF' },
];

const tableData = computed(() =>
    (props.mouvements.data || []).map((item) => ({
        ...item,
        collaborateur: item.profil ? `${item.profil.prenom} ${item.profil.nom}` : '—',
        detail: item.fonction_avant || item.fonction_apres
            ? `${item.fonction_avant || '—'} → ${item.fonction_apres || '—'}`
            : '—',
    })),
);
</script>

<template>
    <Head title="Mouvements RH" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Arrivées, départs et changements de poste</h1>
                    <p class="mt-1 text-sm text-gray-600">Historique des mouvements du personnel.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Link href="/profils/create">
                        <Button class="w-full bg-green-600 hover:bg-green-700 sm:w-auto">
                            <UserPlus class="mr-2 h-4 w-4" />
                            Arrivée
                        </Button>
                    </Link>
                    <Link href="/profils/mouvements/changement-poste">
                        <Button class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto">
                            <ArrowLeftRight class="mr-2 h-4 w-4" />
                            Changement de poste
                        </Button>
                    </Link>
                    <Link href="/profils/mouvements/depart">
                        <Button class="w-full bg-red-600 hover:bg-red-700 sm:w-auto">
                            <UserMinus class="mr-2 h-4 w-4" />
                            Départ
                        </Button>
                    </Link>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
                <div class="mb-4 flex items-center gap-2">
                    <Filter class="h-5 w-5 text-gray-500" />
                    <h2 class="text-base font-semibold text-gray-700">Filtres</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Type</label>
                        <select v-model="filters.type" class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm">
                            <option value="">Tous</option>
                            <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Recherche</label>
                        <Input
                            v-model="filters.search"
                            type="text"
                            placeholder="Nom, prénom, matricule"
                            class="border-gray-300"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <Button class="bg-blue-600 hover:bg-blue-700" @click="applyFilters">Appliquer</Button>
                    <Button
                        variant="outline"
                        @click="() => { filters.type = ''; filters.search = ''; applyFilters(); }"
                    >
                        Réinitialiser
                    </Button>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="tableData"
                :current-page="currentPage"
                :items-per-page="perPage"
                :total-items="totalItems"
                @page-change="handlePageChange"
                @items-per-page-change="handleItemsPerPageChange"
            >
                <template #item.date_effet="{ item }">
                    <span class="text-gray-900">{{ formatDate(item.date_effet) }}</span>
                </template>
                <template #item.collaborateur="{ item }">
                    <Link
                        v-if="item.profil"
                        :href="`/profils/${item.profil.id}`"
                        class="font-medium text-blue-700 hover:underline"
                    >
                        {{ item.collaborateur }}
                    </Link>
                    <span v-else>{{ item.collaborateur }}</span>
                    <div v-if="item.profil" class="text-xs text-gray-500">{{ item.profil.matricule }}</div>
                </template>
                <template #item.type_label="{ item }">
                    <span :class="['rounded-full px-3 py-1 text-xs font-medium', typeBadge(item.type)]">
                        {{ item.type_label }}
                    </span>
                </template>
                <template #item.detail="{ item }">
                    <div class="text-sm text-gray-800">{{ item.detail }}</div>
                    <div v-if="item.departement_avant !== item.departement_apres" class="text-xs text-gray-500">
                        {{ item.departement_avant || '—' }} → {{ item.departement_apres || '—' }}
                    </div>
                </template>
                <template #item.motif="{ item }">
                    <span class="text-gray-700">{{ item.motif || '—' }}</span>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
