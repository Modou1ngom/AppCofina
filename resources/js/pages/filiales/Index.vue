<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Code, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Filiale {
    id: number;
    nom: string;
    description?: string;
    actif: boolean;
    profils_count?: number;
}

interface Props {
    filiales: Filiale[];
}

const props = defineProps<Props>();

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
    return props.filiales.map(filiale => ({
        id: filiale.id,
        nom: filiale.nom,
        description: filiale.description || '-',
        actif: filiale.actif,
        filiale: filiale,
    }));
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Filiales',
        href: '#',
    },
];

const deleteFiliale = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette filiale ?')) {
        router.delete(`/filiales/${id}`);
    }
};
</script>

<template>
    <Head title="Filiales" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Liste des filiales</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <Link href="/filiales/create">
                    <Button>Nouvelle filiale</Button>
                </Link>
            </div>

            <DataTable
                :headers="columns"
                :items="tableData"
                :current-page="1"
                :items-per-page="5"
                :total-items="tableData.length"
                show-select
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
                            :href="`/filiales/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/filiales/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteFiliale(item.id)"
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

