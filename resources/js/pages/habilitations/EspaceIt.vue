<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { etape6 } from '@/routes/habilitations';
import { computed, ref } from 'vue';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { UserCheck, Play, CheckCircle, Search, Filter, Eye } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

interface Habilitation {
    id: number;
    status: string;
    request_type: string;
    requester: {
        nom: string;
        prenom: string;
    };
    beneficiary: {
        nom: string;
        prenom: string;
    };
    created_at: string;
    executor_it_id?: number | null;
}

interface Props {
    habilitations: {
        data: Habilitation[];
        links: any;
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
    filter?: string;
    stats: {
        approuvees: number;
        en_cours: number;
        terminees: number;
    };
}

const props = defineProps<Props>();

const page = usePage();
const auth = computed(() => page.props.auth as any);

// Filtre de recherche
const search = ref('');

const applySearch = () => {
    const params = new URLSearchParams(window.location.search);
    if (search.value) {
        params.set('search', search.value);
    } else {
        params.delete('search');
    }
    params.set('page', '1');
    router.visit(`/habilitations/espace-it?${params.toString()}`, { preserveScroll: true });
};

// Fonction pour prendre en charge une habilitation
const prendreEnCharge = (habilitationId: number) => {
    if (confirm('Voulez-vous prendre en charge cette habilitation ?')) {
        router.post(`/habilitations/${habilitationId}/prendre-en-charge`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['habilitations', 'stats'] });
            },
        });
    }
};

// Fonction pour vérifier si l'utilisateur peut prendre en charge
const canTakeCharge = (habilitation: Habilitation) => {
    return habilitation.status === 'approved';
};

// Fonction pour vérifier si l'utilisateur peut exécuter
const canExecute = (habilitation: Habilitation) => {
    return habilitation.status === 'in_progress' && habilitation.executor_it_id === auth.value?.user?.id;
};

const getStatutBadge = (status: string) => {
    const badges: Record<string, string> = {
        approved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        in_progress: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    };
    return badges[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
};

const getStatutLabel = (status: string) => {
    const labels: Record<string, string> = {
        approved: 'Approuvée',
        in_progress: 'En cours d\'exécution',
        completed: 'Terminée',
    };
    return labels[status] || status;
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

const currentPage = computed(() => props.habilitations.current_page || props.habilitations.meta?.current_page || 1);
const totalItems = computed(() => props.habilitations.total || props.habilitations.meta?.total || 0);
const perPage = computed(() => props.habilitations.per_page || props.habilitations.meta?.per_page || 10);

const columns: Column[] = [
    {
        key: 'id',
        title: 'ID',
        sortable: true,
    },
    {
        key: 'beneficiary',
        title: 'BÉNÉFICIAIRE',
        sortable: true,
        width: 'min-w-[200px]',
    },
    {
        key: 'request_type',
        title: 'TYPE',
    },
    {
        key: 'requester',
        title: 'DEMANDEUR',
    },
    {
        key: 'status',
        title: 'STATUT',
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
    return props.habilitations.data.map((item) => ({
        id: item.id,
        habilitation: item,
        beneficiary: `${item.beneficiary.prenom} ${item.beneficiary.nom}`,
        request_type: item.request_type,
        requester: `${item.requester.prenom} ${item.requester.nom}`,
        status: item.status,
        created_at: new Date(item.created_at).toLocaleDateString('fr-FR'),
    }));
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Espace IT',
        href: '#',
    },
];

const filterLabel = computed(() => {
    const labels: Record<string, string> = {
        approuvees: 'Approuvées',
        en_cours: 'En cours',
        terminees: 'Terminées',
    };
    return labels[props.filter || 'approuvees'] || 'Approuvées';
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Espace IT - Habilitations" />

        <div class="space-y-6">
            <!-- En-tête avec statistiques -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Espace IT</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Gérez les habilitations approuvées par le contrôle
                    </p>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Approuvées</p>
                            <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ stats.approuvees }}
                            </p>
                        </div>
                        <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                            <CheckCircle class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">En cours</p>
                            <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {{ stats.en_cours }}
                            </p>
                        </div>
                        <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                            <Play class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Terminées</p>
                            <p class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {{ stats.terminees }}
                            </p>
                        </div>
                        <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                            <CheckCircle class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-4">
                    <!-- Filtres par statut -->
                    <div class="flex items-center gap-2">
                        <Filter class="h-5 w-5 text-gray-500" />
                        <Link
                            href="/habilitations/espace-it?filter=approuvees"
                            :class="[
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                filter === 'approuvees'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            Approuvées
                        </Link>
                        <Link
                            href="/habilitations/espace-it?filter=en_cours"
                            :class="[
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                filter === 'en_cours'
                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            En cours
                        </Link>
                        <Link
                            href="/habilitations/espace-it?filter=terminees"
                            :class="[
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                filter === 'terminees'
                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            Terminées
                        </Link>
                    </div>

                    <!-- Recherche -->
                    <div class="flex flex-1 items-center gap-2">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                v-model="search"
                                type="text"
                                placeholder="Rechercher par nom, prénom..."
                                class="pl-10"
                                @keyup.enter="applySearch"
                            />
                        </div>
                        <Button @click="applySearch" variant="outline">
                            Rechercher
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Tableau des habilitations -->
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <DataTable
                    :columns="columns"
                    :items="tableData"
                    :items-per-page="perPage"
                    :total-items="totalItems"
                    :current-page="currentPage"
                >
                    <template #item.beneficiary="{ item }">
                        <div class="flex items-center gap-3">
                            <Avatar class="flex-shrink-0 h-10 w-10">
                                <AvatarImage :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(item.beneficiary)}&background=random`" />
                                <AvatarFallback :class="getAvatarColor(item.beneficiary)">
                                    {{ getInitials(item.beneficiary) }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="font-semibold text-black dark:text-white" style="color: #000 !important;">{{ item.beneficiary || 'N/A' }}</span>
                        </div>
                    </template>

                    <template #item.status="{ item }">
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                getStatutBadge(item.status)
                            ]"
                        >
                            {{ getStatutLabel(item.status) }}
                        </span>
                    </template>

                    <template #item.actions="{ item }">
                        <div class="flex items-center gap-1">
                            <button
                                v-if="canTakeCharge(item.habilitation)"
                                @click="prendreEnCharge(item.id)"
                                class="inline-flex items-center justify-center rounded-md p-2 text-purple-600 hover:bg-purple-50 hover:text-purple-700 transition-colors"
                                title="Prendre en charge"
                            >
                                <UserCheck class="h-5 w-5" />
                            </button>
                            <Link
                                v-if="canExecute(item.habilitation)"
                                :href="etape6.url({ habilitation: item.id })"
                                class="inline-flex items-center justify-center rounded-md p-2 text-green-600 hover:bg-green-50 hover:text-green-700 transition-colors"
                                title="Exécuter"
                            >
                                <Play class="h-5 w-5" />
                            </Link>
                            <Link
                                :href="`/habilitations/${item.id}`"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                                title="Voir les détails"
                            >
                                <Eye class="h-5 w-5" />
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

