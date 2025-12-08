<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Code, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Agence {
    id: number;
    nom: string;
    code_agent: string;
    description?: string;
    actif: boolean;
    profils_count?: number;
}

interface Props {
    agences: {
        data: Agence[];
        links?: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
}

const props = defineProps<Props>();

const currentPage = computed(() => props.agences.current_page || props.agences.meta?.current_page || 1);
const totalItems = computed(() => props.agences.total || props.agences.meta?.total || 0);
const perPage = computed(() => props.agences.per_page || props.agences.meta?.per_page || 5);

const handlePageChange = (page: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page.toString());
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    const newUrl = `/agences?${urlParams.toString()}`;
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['agences'],
        replace: false,
    });
};

const handleItemsPerPageChange = (items: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', items.toString());
    urlParams.set('page', '1');
    const newUrl = `/agences?${urlParams.toString()}`;
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['agences'],
        replace: false,
    });
};

const getStatusBadge = (actif: boolean) => {
    if (actif) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
};

const getStatusLabel = (actif: boolean) => {
    return actif ? 'Actif' : 'Inactif';
};

const columns: Column[] = [
    {
        key: 'nom',
        title: 'NAME',
        sortable: true,
    },
    {
        key: 'code_agent',
        title: 'CODE AGENT',
        sortable: true,
    },
    {
        key: 'description',
        title: 'DESCRIPTION',
    },
    {
        key: 'actif',
        title: 'STATUS',
    },
    {
        key: 'actions',
        title: 'ACTIONS',
    },
];

const tableData = computed(() => {
    const agencesData = props.agences.data || props.agences;
    const agencesArray = Array.isArray(agencesData) ? agencesData : [];
    return agencesArray.map(agence => ({
        id: agence.id,
        nom: agence.nom,
        code_agent: agence.code_agent,
        description: agence.description || '-',
        actif: agence.actif,
        agence: agence,
    }));
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agences',
        href: '#',
    },
];

const deleteAgence = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette agence ?')) {
        router.delete(`/agences/${id}`);
    }
};
</script>

<template>
    <Head title="Agences" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Liste des agences</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <Link href="/agences/create">
                    <Button>Nouvelle agence</Button>
                </Link>
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
                <template #item.nom="{ item }">
                    <span class="text-gray-900 font-medium">{{ item.nom }}</span>
                </template>

                <template #item.code_agent="{ item }">
                    <span class="text-gray-900 font-mono text-sm">{{ item.code_agent }}</span>
                </template>

                <template #item.description="{ item }">
                    <span class="text-gray-900">{{ item.description }}</span>
                </template>

                <template #item.actif="{ item }">
                    <span
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-medium',
                            getStatusBadge(item.actif),
                        ]"
                    >
                        {{ getStatusLabel(item.actif) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/agences/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/agences/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteAgence(item.id)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors"
                            title="Supprimer"
                        >
                            <Trash2 class="h-5 w-5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>

