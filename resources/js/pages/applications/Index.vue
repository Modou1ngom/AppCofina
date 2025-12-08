<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Code, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Application {
    id: number;
    nom: string;
    description?: string;
    actif: boolean;
    ordre: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    applications: {
        data: Application[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
}

const props = defineProps<Props>();

const currentPage = computed(() => props.applications.current_page || props.applications.meta?.current_page || 1);
const totalItems = computed(() => props.applications.total || props.applications.meta?.total || 0);
const perPage = computed(() => props.applications.per_page || props.applications.meta?.per_page || 5);

const getStatusBadge = (actif: boolean) => {
    if (actif) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
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
        key: 'ordre',
        title: 'ORDRE',
        sortable: true,
    },
    {
        key: 'actif',
        title: 'STATUS',
    },
    {
        key: 'created_at',
        title: 'DATE',
        sortable: true,
    },
    {
        key: 'actions',
        title: 'ACTIONS',
    },
];

const tableData = computed(() => {
    return props.applications.data.map(application => ({
        id: application.id,
        nom: application.nom,
        description: application.description || 'Aucune description',
        ordre: application.ordre,
        actif: application.actif,
        created_at: new Date(application.created_at).toLocaleDateString('fr-FR'),
        application: application,
    }));
});

const handlePageChange = (page: number) => {
    // Récupérer tous les paramètres actuels
    const urlParams = new URLSearchParams(window.location.search);
    
    // Mettre à jour le paramètre page
    urlParams.set('page', page.toString());
    
    // Préserver per_page s'il existe
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    
    // Construire l'URL complète
    const newUrl = `/applications?${urlParams.toString()}`;
    
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['applications'],
        replace: false,
    });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const handleSort = (column: string, direction: 'asc' | 'desc') => {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', column);
    url.searchParams.set('direction', direction);
    router.visit(url.toString(), { preserveScroll: true });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Applications',
        href: '#',
    },
];

const deleteApplication = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette application ?')) {
        router.delete(`/applications/${id}`);
    }
};
</script>

<template>
    <Head title="Applications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Liste des applications</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <Link href="/applications/create">
                    <Button>Nouvelle application</Button>
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
                @sort="handleSort"
            >
                <template #item.nom="{ item }">
                    <span class="text-gray-900 font-medium">{{ item.nom }}</span>
                </template>

                <template #item.description="{ item }">
                    <span class="text-gray-900">{{ item.description }}</span>
                </template>

                <template #item.ordre="{ item }">
                    <span class="text-gray-900">{{ item.ordre }}</span>
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

                <template #item.created_at="{ item }">
                    <span class="text-gray-900">{{ item.created_at }}</span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/applications/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/applications/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteApplication(item.id)"
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

