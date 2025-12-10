<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { create, etape3, etape4, etape5, etape6 } from '@/routes/habilitations';
import { computed, ref, watch } from 'vue';
import { useBeneficiaryDialog } from '@/composables/useBeneficiaryDialog';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { Code, Eye, CheckCircle, Shield, Play, FileCheck, Filter, UserCheck } from 'lucide-vue-next';
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
        n_plus_1_id?: number | null;
        n_plus_2_id?: number | null;
    };
    created_at: string;
}

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
    fonction?: string;
    departement?: string;
    email?: string;
    telephone?: string;
    site?: string;
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
    subordonnes?: Profil[];
    hasDepartement?: boolean;
}

const props = defineProps<Props>();

const page = usePage();
const { openDialog, isOpen } = useBeneficiaryDialog();

// Créer un computed pour la réactivité
const isDialogOpen = computed(() => isOpen.value);

// Ouvrir automatiquement le dialog si on vient du menu avec openDialog=true
watch(() => page.url, (url) => {
    if (url.includes('openDialog=true') && props.hasDepartement && props.subordonnes && props.subordonnes.length > 0) {
        openDialog(props.subordonnes);
    }
}, { immediate: true });

const isControle = computed(() => (page.props.auth as any)?.isControle || false);
const isMetier = computed(() => (page.props.auth as any)?.isMetier || false);
const isAdmin = computed(() => (page.props.auth as any)?.isAdmin || false);
const isExecuteurIt = computed(() => (page.props.auth as any)?.isExecuteurIt || false);
const currentProfil = computed(() => (page.props.auth as any)?.profil || null);

// Fonction pour vérifier si l'utilisateur peut valider N+1 (admin peut voir mais pas valider)
const canValidateN1 = (habilitation: Habilitation) => {
    if (habilitation.status !== 'pending_n1') return false;
    if (!currentProfil.value || !habilitation.beneficiary) return false;
    return habilitation.beneficiary.n_plus_1_id === currentProfil.value.id;
};

// Fonction pour vérifier si l'utilisateur peut valider N+2 (admin peut voir mais pas valider)
const canValidateN2 = (habilitation: Habilitation) => {
    if (habilitation.status !== 'pending_n2') return false;
    if (!currentProfil.value || !habilitation.beneficiary) return false;
    return habilitation.beneficiary.n_plus_2_id === currentProfil.value.id;
};

// Fonction pour vérifier si l'utilisateur peut valider le Contrôle (admin peut voir mais pas valider)
const canValidateControl = (habilitation: Habilitation) => {
    return isControle.value && habilitation.status === 'pending_control';
};

// Fonction pour vérifier si l'utilisateur peut prendre en charge une habilitation (exécuteur IT)
const canTakeCharge = (habilitation: Habilitation) => {
    return isExecuteurIt.value && habilitation.status === 'approved';
};

// Fonction pour vérifier si l'utilisateur peut exécuter IT (admin ou exécuteur IT)
const canExecuteIT = (habilitation: Habilitation) => {
    return (isAdmin.value || isExecuteurIt.value) && (habilitation.status === 'approved' || habilitation.status === 'in_progress');
};

const filterLabel = computed(() => {
    const filter = props.filter || page.url.split('filter=')[1]?.split('&')[0] || 'all';
    const labels: Record<string, string> = {
        all: 'Toutes les habilitations',
        encours: 'Habilitations en cours',
        termine: 'Habilitations terminées',
        rejete: 'Habilitations rejetées',
    };
    return labels[filter] || 'Toutes les habilitations';
});

// Filtres avancés
const filters = ref({
    status: '',
    request_type: '',
    search: '',
});

const applyFilters = () => {
    const params = new URLSearchParams(window.location.search);
    
    // Préserver le filtre existant si présent
    if (props.filter && props.filter !== 'all') {
        params.set('filter', props.filter);
    }
    
    // Ajouter les nouveaux filtres
    if (filters.value.status) {
        params.set('status', filters.value.status);
    } else {
        params.delete('status');
    }
    
    if (filters.value.request_type) {
        params.set('request_type', filters.value.request_type);
    } else {
        params.delete('request_type');
    }
    
    if (filters.value.search) {
        params.set('search', filters.value.search);
    } else {
        params.delete('search');
    }
    
    params.set('page', '1');
    router.visit(`/habilitations?${params.toString()}`, { preserveScroll: true });
};

// Initialiser les filtres depuis l'URL
const initializeFilters = () => {
    const urlParams = new URLSearchParams(window.location.search);
    filters.value.status = urlParams.get('status') || '';
    filters.value.request_type = urlParams.get('request_type') || '';
    filters.value.search = urlParams.get('search') || '';
};

// Initialiser au chargement
initializeFilters();

// Fonction pour prendre en charge une habilitation
const prendreEnCharge = (habilitationId: number) => {
    if (confirm('Voulez-vous prendre en charge cette habilitation ?')) {
        router.post(`/habilitations/${habilitationId}/prendre-en-charge`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Recharger la page pour mettre à jour les données
                router.reload({ only: ['habilitations'] });
            },
        });
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: filterLabel.value,
        href: '#',
    },
];

const getStatutBadge = (status: string) => {
    const badges: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        pending_n1: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        pending_control: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        pending_n2: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        approved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        in_progress: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        completed: 'bg-green-200 text-green-900 dark:bg-green-800 dark:text-green-100',
    };
    return badges[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
};

const getStatutLabel = (status: string) => {
    const labels: Record<string, string> = {
        draft: 'Brouillon',
        pending_n1: 'En attente N+1',
        pending_control: 'En attente Contrôle',
        pending_n2: 'En attente N+2',
        approved: 'Approuvée',
        rejected: 'Rejetée',
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
const perPage = computed(() => props.habilitations.per_page || props.habilitations.meta?.per_page || 5);

const columns: Column[] = [
    {
        key: 'id',
        title: 'ID',
        sortable: true,
    },
    {
        key: 'beneficiary',
        title: 'NAME',
        sortable: true,
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
        key: 'created_at',
        title: 'DATE',
        sortable: true,
    },
    {
        key: 'status',
        title: 'STATUS',
    },
    {
        key: 'actions',
        title: 'ACTIONS',
    },
];

const tableData = computed(() => {
    return props.habilitations.data.map(habilitation => ({
        id: habilitation.id,
        beneficiary: `${habilitation.beneficiary.prenom} ${habilitation.beneficiary.nom}`,
        request_type: habilitation.request_type,
        requester: `${habilitation.requester.prenom} ${habilitation.requester.nom}`,
        created_at: new Date(habilitation.created_at).toLocaleDateString('fr-FR'),
        status: habilitation.status,
        habilitation: habilitation,
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
    const newUrl = `/habilitations?${urlParams.toString()}`;
    
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['habilitations'],
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
    <Head title="Demandes d'habilitation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Contenu de la page (masqué si le dialog est ouvert) -->
        <div v-if="!isDialogOpen" class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900">Liste des habilitations</h1>
                    <Code class="h-5 w-5 text-gray-500" />
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
                            v-model="filters.status"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous les statuts</option>
                            <option value="draft">Brouillon</option>
                            <option value="pending_n1">En attente N+1</option>
                            <option value="pending_control">En attente Contrôle</option>
                            <option value="pending_n2">En attente N+2</option>
                            <option value="approved">Approuvée</option>
                            <option value="rejected">Rejetée</option>
                            <option value="in_progress">En cours d'exécution</option>
                            <option value="completed">Terminée</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Type de demande</label>
                        <select
                            v-model="filters.request_type"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous les types</option>
                            <option value="Creation">Création</option>
                            <option value="Modification">Modification</option>
                            <option value="Desactivation">Désactivation</option>
                            <option value="Suppression">Suppression</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Recherche</label>
                        <Input
                            v-model="filters.search"
                            type="text"
                            placeholder="Rechercher (bénéficiaire, demandeur)"
                            class="border-gray-300 focus-visible:border-gray-400"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <Button @click="applyFilters" class="bg-blue-600 hover:bg-blue-700">
                        Appliquer les filtres
                    </Button>
                    <Button variant="outline" @click="() => { filters.status = ''; filters.request_type = ''; filters.search = ''; applyFilters(); }" class="border-gray-300">
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
                @sort="handleSort"
            >
                <template #item.id="{ item }">
                    <span class="text-gray-900">#{{ item.id }}</span>
                </template>

                <template #item.beneficiary="{ item }">
                    <div class="flex items-center gap-3">
                        <Avatar class="h-10 w-10">
                            <AvatarImage :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(item.beneficiary)}&background=random`" />
                            <AvatarFallback :class="getAvatarColor(item.beneficiary)">
                                {{ getInitials(item.beneficiary) }}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <div class="font-medium text-gray-900">{{ item.beneficiary }}</div>
                            <div class="text-xs text-gray-500">Bénéficiaire</div>
                        </div>
                    </div>
                </template>

                <template #item.request_type="{ item }">
                    <span class="text-gray-900">{{ item.request_type }}</span>
                </template>

                <template #item.requester="{ item }">
                    <span class="text-gray-900">{{ item.requester }}</span>
                </template>

                <template #item.created_at="{ item }">
                    <span class="text-gray-900">{{ item.created_at }}</span>
                </template>

                <template #item.status="{ item }">
                    <span
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-medium',
                            getStatutBadge(item.status),
                        ]"
                    >
                        {{ getStatutLabel(item.status) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1 flex-wrap">
                        <Link
                            :href="`/habilitations/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            v-if="canValidateN1(item.habilitation)"
                            :href="etape3.url({ habilitation: item.id })"
                            class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                            title="Valider N+1"
                        >
                            <CheckCircle class="h-5 w-5" />
                        </Link>
                        <Link
                            v-if="canValidateN2(item.habilitation)"
                            :href="etape4.url({ habilitation: item.id })"
                            class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                            title="Valider N+2"
                        >
                            <CheckCircle class="h-5 w-5" />
                        </Link>
                        <Link
                            v-if="canValidateControl(item.habilitation)"
                            :href="etape5.url({ habilitation: item.id })"
                            class="inline-flex items-center justify-center rounded-md p-2 text-yellow-600 hover:bg-yellow-50 hover:text-yellow-700 transition-colors"
                            title="Contrôler"
                        >
                            <Shield class="h-5 w-5" />
                        </Link>
                        <button
                            v-if="canTakeCharge(item.habilitation)"
                            @click="prendreEnCharge(item.id)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-purple-600 hover:bg-purple-50 hover:text-purple-700 transition-colors"
                            title="Prendre en charge"
                        >
                            <UserCheck class="h-5 w-5" />
                        </button>
                        <Link
                            v-if="item.status === 'pending_control' && isControle"
                            :href="etape5.url({ habilitation: item.id })"
                            class="inline-flex items-center justify-center rounded-md p-2 text-yellow-600 hover:bg-yellow-50 hover:text-yellow-700 transition-colors"
                            title="Contrôler"
                        >
                            <FileCheck class="h-5 w-5" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>


