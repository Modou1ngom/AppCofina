<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { ref, computed } from 'vue';
import { Code, Eye, Pencil, Trash2, RefreshCw, Lock, Unlock } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    profil?: {
        id: number;
        nom: string;
        prenom: string;
        matricule: string;
        site?: string;
    };
}

interface Props {
    users: {
        data: User[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
    environnements?: Array<{ id: number; nom: string }>;
    departements?: Array<{ id: number; nom: string }>;
    roles?: Array<{ id: number; nom: string }>;
    agences?: Array<{ id: number; nom: string }>;
    profils?: Array<{ id: number; nom: string; prenom: string; matricule: string; site?: string }>;
}

const props = defineProps<Props>();

const filters = ref({
    environnement: '',
    departement: '',
    role: '',
    agence: '',
    profil: '',
    activation: '',
    search: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: '#',
    },
];

const currentPage = computed(() => {
    return props.users.current_page || props.users.meta?.current_page || 1;
});
const totalItems = computed(() => {
    // Laravel paginate() retourne total à la racine, pas dans meta
    const total = props.users.total || props.users.meta?.total || 0;
    return total;
});
const perPage = computed(() => {
    return props.users.per_page || props.users.meta?.per_page || 5;
});

const deleteUser = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
        router.delete(`/users/${id}`);
    }
};

const toggleUser = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir changer le statut de cet utilisateur ?')) {
        router.post(`/users/${id}/toggle`, {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['users'],
            onSuccess: () => {
                // Le message de succès est géré par le contrôleur
            },
        });
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

const getStatusBadge = (isActive: boolean) => {
    if (isActive) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
    return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
};

const getStatusLabel = (isActive: boolean) => {
    return isActive ? 'Actif' : 'Inactif';
};

const columns: Column[] = [
    {
        key: 'name',
        title: 'NOM',
        sortable: true,
    },
    {
        key: 'flexcube',
        title: 'Matricule',
        sortable: true,
    },
    {
        key: 'email',
        title: 'EMAIL',
        sortable: true,
    },
    {
        key: 'agence',
        title: 'AGENCE',
        sortable: true,
    },
    {
        key: 'profil',
        title: 'PROFIL',
    },
    {
        key: 'is_active',
        title: 'ACTIVATION',
    },
    {
        key: 'actions',
        title: 'ACTIONS',
    },
];

const tableData = computed(() => {
    return props.users.data.map(user => ({
        id: user.id,
        name: user.name,
        flexcube: user.profil?.matricule || user.email?.split('@')[0] || '-',
        email: user.email,
        agence: user.profil?.site || '-',
        profil: user.profil ? `${user.profil.prenom} ${user.profil.nom}` : 'Metier',
        is_active: user.is_active,
        user: user,
    }));
});

const reload = () => {
    router.reload({ only: ['users'] });
};

const applyFilters = () => {
    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });
    // Réinitialiser à la page 1 lors de l'application des filtres
    params.set('page', '1');
    router.visit(`/users?${params.toString()}`, { preserveScroll: true });
};

// Initialiser les filtres depuis l'URL
const initializeFilters = () => {
    const urlParams = new URLSearchParams(window.location.search);
    filters.value.environnement = urlParams.get('environnement') || '';
    filters.value.departement = urlParams.get('departement') || '';
    filters.value.role = urlParams.get('role') || '';
    filters.value.agence = urlParams.get('agence') || '';
    filters.value.profil = urlParams.get('profil') || '';
    filters.value.activation = urlParams.get('activation') || '';
    filters.value.search = urlParams.get('search') || '';
};

// Initialiser au chargement
initializeFilters();

const handlePageChange = (page: number) => {
    console.log('handlePageChange called:', { page, currentPage: currentPage.value, totalItems: totalItems.value, perPage: perPage.value });
    
    // Récupérer tous les paramètres actuels
    const urlParams = new URLSearchParams(window.location.search);
    
    // Mettre à jour le paramètre page
    urlParams.set('page', page.toString());
    
    // Préserver per_page s'il existe
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    
    // Construire l'URL complète
    const newUrl = `/users?${urlParams.toString()}`;
    
    console.log('Navigating to:', newUrl);
    
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['users'],
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
</script>

<template>
    <Head title="Utilisateurs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Liste des utilisateurs</h1>

            <!-- Section Filtres -->
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="mb-4 text-base font-semibold text-gray-700">Filtres</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Environnement</label>
                        <select
                            v-model="filters.environnement"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous</option>
                            <option
                                v-for="env in props.environnements || []"
                                :key="env.id"
                                :value="env.id"
                            >
                                {{ env.nom }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Departement</label>
                        <select
                            v-model="filters.departement"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous</option>
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
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Role</label>
                        <select
                            v-model="filters.role"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous</option>
                            <option
                                v-for="role in props.roles || []"
                                :key="role.id"
                                :value="role.id"
                            >
                                {{ role.nom }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Agence</label>
                        <select
                            v-model="filters.agence"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Toutes</option>
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
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Profil</label>
                        <select
                            v-model="filters.profil"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous</option>
                            <option
                                v-for="profil in props.profils || []"
                                :key="profil.id"
                                :value="profil.id"
                            >
                                {{ profil.prenom }} {{ profil.nom }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Activation</label>
                        <select
                            v-model="filters.activation"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous</option>
                            <option value="1">Activé</option>
                            <option value="0">Désactivé</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <Input
                        v-model="filters.search"
                        type="text"
                        placeholder="Rechercher (nom, email)"
                        class="flex-1 border-gray-300 focus-visible:border-gray-400"
                        @keyup.enter="applyFilters"
                    />
                    <Button @click="applyFilters" class="bg-blue-600 hover:bg-blue-700">
                        Appliquer les filtres
                    </Button>
                    <Button variant="outline" @click="() => { filters.environnement = ''; filters.departement = ''; filters.role = ''; filters.agence = ''; filters.profil = ''; filters.activation = ''; filters.search = ''; applyFilters(); }" class="border-gray-300">
                        Réinitialiser
                    </Button>
                    <Link href="/users/create">
                        <Button class="bg-purple-600 hover:bg-purple-700">+ Nouveau</Button>
                    </Link>
                    
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
                @sort="handleSort"
            >
                <template #item.name="{ item }">
                    <span class="text-gray-900 font-medium">{{ item.name }}</span>
                </template>

                <template #item.flexcube="{ item }">
                    <span class="text-gray-900">{{ item.flexcube }}</span>
                </template>

                <template #item.email="{ item }">
                    <span class="text-gray-900">{{ item.email }}</span>
                </template>

                <template #item.agence="{ item }">
                    <span class="text-gray-900">{{ item.agence }}</span>
                </template>

                <template #item.profil="{ item }">
                    <span class="text-gray-900">{{ item.profil }}</span>
                </template>

                <template #item.is_active="{ item }">
                    <div class="flex items-center gap-2">
                        <component 
                            :is="item.is_active ? Unlock : Lock" 
                            :class="[
                                'h-5 w-5',
                                item.is_active ? 'text-green-600' : 'text-gray-400'
                            ]" 
                        />
                        <span 
                            :class="[
                                'text-base font-medium',
                                item.is_active ? 'text-green-700' : 'text-gray-500'
                            ]"
                        >
                            {{ item.is_active ? 'Activé' : 'Désactivé' }}
                        </span>
                    </div>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/users/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/users/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="toggleUser(item.id)"
                            :class="[
                                'inline-flex items-center justify-center rounded-md p-2 transition-colors',
                                item.is_active 
                                    ? 'text-orange-600 hover:bg-orange-50 hover:text-orange-700' 
                                    : 'text-green-600 hover:bg-green-50 hover:text-green-700'
                            ]"
                            :title="item.is_active ? 'Désactiver' : 'Activer'"
                        >
                            <component :is="item.is_active ? Lock : Unlock" class="h-5 w-5" />
                        </button>
                        <button
                            @click="deleteUser(item.id)"
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

