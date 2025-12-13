<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { computed } from 'vue';
import { Code, Eye, Pencil, Trash2, Filter, Upload, Download } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { ref } from 'vue';

interface Profil {
    id: number;
    matricule: string;
    prenom: string;
    nom: string;
    fonction?: string;
    departement?: string;
    email?: string;
    telephone?: string;
    site?: string;
    type_contrat: string;
    statut: string;
}

interface Props {
    profils: {
        data: Profil[];
        links?: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
    departements?: Array<{ id: number; nom: string }>;
    agences?: Array<{ id: number; nom: string }>;
}

const props = defineProps<Props>();

// Filtres
const filters = ref({
    statut: '',
    departement: '',
    fonction: '',
    site: '',
    type_contrat: '',
    search: '',
});

const applyFilters = () => {
    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });
    params.set('page', '1');
    router.visit(`/profils?${params.toString()}`, { preserveScroll: true });
};

const exportProfils = () => {
    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });
    window.location.href = `/profils/export?${params.toString()}`;
};

// Initialiser les filtres depuis l'URL
const initializeFilters = () => {
    const urlParams = new URLSearchParams(window.location.search);
    filters.value.statut = urlParams.get('statut') || '';
    filters.value.departement = urlParams.get('departement') || '';
    filters.value.fonction = urlParams.get('fonction') || '';
    filters.value.site = urlParams.get('site') || '';
    filters.value.type_contrat = urlParams.get('type_contrat') || '';
    filters.value.search = urlParams.get('search') || '';
};

// Initialiser au chargement
initializeFilters();

const currentPage = computed(() => props.profils.current_page || props.profils.meta?.current_page || 1);
const totalItems = computed(() => props.profils.total || props.profils.meta?.total || 0);
const perPage = computed(() => props.profils.per_page || props.profils.meta?.per_page || 5);

const handlePageChange = (page: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page.toString());
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    const newUrl = `/profils?${urlParams.toString()}`;
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['profils'],
        replace: false,
    });
};

const handleItemsPerPageChange = (items: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', items.toString());
    urlParams.set('page', '1');
    const newUrl = `/profils?${urlParams.toString()}`;
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['profils'],
        replace: false,
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profils',
        href: '#',
    },
];

const deleteProfil = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce profil ?')) {
        router.delete(`/profils/${id}`);
    }
};

const getAvatarColor = (name: string) => {
    const colors = [
        'bg-purple-500',
        'bg-blue-500',
        'bg-green-500',
        'bg-yellow-500',
        'bg-pink-500',
        'bg-indigo-500',
        'bg-red-500',
        'bg-teal-500',
    ];
    const index = name.charCodeAt(0) % colors.length;
    return colors[index];
};

const getStatusBadge = (statut: string) => {
    if (statut === 'actif') {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
};

const getStatusLabel = (statut: string) => {
    return statut === 'actif' ? 'Actif' : 'Inactif';
};

const columns: Column[] = [
    {
        key: 'name',
        title: 'NAME',
        sortable: true,
    },
    {
        key: 'matricule',
        title: 'MATRICULE',
        sortable: true,
    },
    {
        key: 'fonction',
        title: 'FONCTION',
    },
    {
        key: 'departement',
        title: 'DÉPARTEMENT',
    },
    {
        key: 'statut',
        title: 'STATUS',
    },
    {
        key: 'actions',
        title: 'ACTIONS',
    },
];

const tableData = computed(() => {
    const profilsData = props.profils.data || props.profils;
    const profilsArray = Array.isArray(profilsData) ? profilsData : [];
    return profilsArray.map(profil => ({
        id: profil.id,
        name: `${profil.prenom} ${profil.nom}`,
        matricule: profil.matricule,
        fonction: profil.fonction || '-',
        departement: profil.departement || '-',
        statut: profil.statut,
        profil: profil,
    }));
});
</script>

<template>
    <Head title="Profils" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Liste des profils</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <div class="flex items-center gap-3">
                    <Button
                        @click="exportProfils"
                        class="bg-purple-600 hover:bg-purple-700"
                    >
                        <Download class="mr-2 h-4 w-4" />
                        Exporter Excel
                    </Button>
                    <Button
                        @click="router.visit('/profils/import')"
                        class="bg-green-600 hover:bg-green-700"
                    >
                        <Upload class="mr-2 h-4 w-4" />
                        Importer Excel
                    </Button>
                    <Link
                        href="/profils/create"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Créer un profil
                    </Link>
                </div>
            </div>

            <!-- Section Filtres -->
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="mb-4 flex items-center gap-2">
                    <Filter class="h-5 w-5 text-gray-500" />
                    <h2 class="text-base font-semibold text-gray-700">Filtres</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Statut</label>
                        <select
                            v-model="filters.statut"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous les statuts</option>
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Département</label>
                        <select
                            v-model="filters.departement"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous les départements</option>
                            <option
                                v-for="dept in props.departements || []"
                                :key="dept.id"
                                :value="dept.id"
                            >
                                {{ dept.nom }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Site/Agence</label>
                        <select
                            v-model="filters.site"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous les sites</option>
                            <option
                                v-for="agence in props.agences || []"
                                :key="agence.id"
                                :value="agence.id"
                            >
                                {{ agence.nom }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Fonction</label>
                        <Input
                            v-model="filters.fonction"
                            type="text"
                            placeholder="Rechercher une fonction"
                            class="border-gray-300 focus-visible:border-gray-400"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Type de contrat</label>
                        <select
                            v-model="filters.type_contrat"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous les types</option>
                            <option value="CDI">CDI</option>
                            <option value="CDD">CDD</option>
                            <option value="Stagiaire">Stagiaire</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Recherche</label>
                        <Input
                            v-model="filters.search"
                            type="text"
                            placeholder="Nom, prénom, matricule, email"
                            class="border-gray-300 focus-visible:border-gray-400"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <Button @click="applyFilters" class="bg-blue-600 hover:bg-blue-700">
                        Appliquer les filtres
                    </Button>
                    <Button variant="outline" @click="() => { filters.statut = ''; filters.departement = ''; filters.fonction = ''; filters.site = ''; filters.type_contrat = ''; filters.search = ''; applyFilters(); }" class="border-gray-300">
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
                show-select
                @page-change="handlePageChange"
                @items-per-page-change="handleItemsPerPageChange"
            >
                <template #item.name="{ item }">
                    <div class="flex items-center gap-3">
                        <Avatar class="h-10 w-10">
                            <AvatarImage :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(item.name)}&background=random`" />
                            <AvatarFallback :class="getAvatarColor(item.name)">
                                {{ getInitials(item.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <div class="font-medium text-gray-900">{{ item.name }}</div>
                            <div class="text-xs text-gray-500">{{ item.fonction || 'Employé' }}</div>
                        </div>
                    </div>
                </template>

                <template #item.matricule="{ item }">
                    <span class="text-gray-900">{{ item.matricule }}</span>
                </template>

                <template #item.fonction="{ item }">
                    <span class="text-gray-900">{{ item.fonction }}</span>
                </template>

                <template #item.departement="{ item }">
                    <span class="text-gray-900">{{ item.departement }}</span>
                </template>

                <template #item.statut="{ item }">
                    <span
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-medium',
                            getStatusBadge(item.statut),
                        ]"
                    >
                        {{ getStatusLabel(item.statut) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/profils/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/profils/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteProfil(item.id)"
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

