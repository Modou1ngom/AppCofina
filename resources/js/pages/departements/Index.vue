<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Code, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Departement {
    id: number;
    nom: string;
    description?: string;
    actif: boolean;
}

interface Props {
    departements: {
        data: Departement[];
        links?: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
}

const props = defineProps<Props>();

const currentPage = computed(() => props.departements.current_page || props.departements.meta?.current_page || 1);
const totalItems = computed(() => props.departements.total || props.departements.meta?.total || 0);
const perPage = computed(() => props.departements.per_page || props.departements.meta?.per_page || 5);

const handlePageChange = (page: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page.toString());
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    const newUrl = `/departements?${urlParams.toString()}`;
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['departements'],
        replace: false,
    });
};

const handleItemsPerPageChange = (items: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', items.toString());
    urlParams.set('page', '1');
    const newUrl = `/departements?${urlParams.toString()}`;
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['departements'],
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
    const departementsData = props.departements.data || props.departements;
    const departementsArray = Array.isArray(departementsData) ? departementsData : [];
    return departementsArray.map(departement => ({
        id: departement.id,
        nom: departement.nom,
        description: departement.description || '-',
        actif: departement.actif,
        departement: departement,
    }));
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Départements',
        href: '#',
    },
];

const deleteDepartement = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce département ?')) {
        router.delete(`/departements/${id}`);
    }
};
</script>

<template>
    <Head title="Départements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Liste des départements</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <Link href="/departements/create">
                    <Button>Nouveau département</Button>
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
                            :href="`/departements/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/departements/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteDepartement(item.id)"
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

