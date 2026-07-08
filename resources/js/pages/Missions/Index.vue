<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import DataTable, { type Column } from '@/components/DataTable.vue';
import MissionDashboardSearch from '@/components/missions/MissionDashboardSearch.vue';
import { formatDateFr, formatNumeroMission } from '@/lib/utils';
import {
    missionPageBg,
    statCardSlate,
    statCardSky,
    statCardEmerald,
    statCardAmber,
    missionCard,
} from '@/lib/missionPastel';
import { Eye, Plus, Pencil, Trash2, AlertCircle, MapPin, BarChart3 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Mission {
    id: number;
    numero_mission?: number | null;
    demandeur_id: number;
    objet: string;
    priorite: string;
    date_debut: string;
    date_fin: string;
    budget: number;
    current_step: string;
    status: string;
    is_demandeur?: boolean;
    is_missionnaire?: boolean;
    etape_libelle?: string;
    peut_modifier_demande?: boolean;
    demandeur?: { name: string };
    beneficiaire?: { name: string };
    created_at: string;
    updated_at: string;
}

interface SitePopulaire {
    site: string;
    count: number;
}

interface Props {
    missions: {
        data: Mission[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
    authUserId: number;
    vueTableauDeBord?: boolean;
    voitToutesLesMissions?: boolean;
    statsMissions?: { total: number; en_cours: number; cloturees: number };
    sitesPopulaires?: SitePopulaire[];
    filtreNumero?: string;
    filtreDemandeur?: string;
}

const props = defineProps<Props>();
const page = usePage();

const csrfTokenForRequest = (): string => {
    const fromProps = (page.props as { csrf_token?: string }).csrf_token;
    if (fromProps) {
        return fromProps;
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const peutModifier = (mission: Mission) => {
    return mission.peut_modifier_demande ?? (mission.is_demandeur && ['renvoye', 'brouillon'].includes(mission.status));
};

const peutSupprimer = (mission: Mission) => {
    return mission.is_demandeur && mission.status !== 'valide';
};

const estMissionnaireSeul = (mission: Mission) => {
    return mission.is_missionnaire && !mission.is_demandeur;
};

const currentPage = computed(() => props.missions.current_page || props.missions.meta?.current_page || 1);
const totalItems = computed(() => props.missions.total || props.missions.meta?.total || 0);
const perPage = computed(() => props.missions.per_page || props.missions.meta?.per_page || 10);

const getStatusBadge = (status: string) => {
    const badges: Record<string, string> = {
        brouillon: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        en_cours: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        renvoye: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        validee: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        rejetee: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        completee: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
        cloture: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    };
    return badges[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
};

const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        brouillon: 'Brouillon',
        en_cours: 'En cours',
        renvoye: 'Renvoyée',
        valide: 'Validée',
        rejete: 'Rejetée',
        validee: 'Validée',
        rejetee: 'Rejetée',
    };
    return labels[status] || status;
};

const getPriorityBadge = (priorite: string) => {
    const badges: Record<string, string> = {
        urgente: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        haute: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        normale: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        basse: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return badges[priorite] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
};

const getPriorityLabel = (priorite: string) => {
    const labels: Record<string, string> = {
        urgente: 'Urgente',
        haute: 'Haute',
        normale: 'Normale',
        basse: 'Basse',
    };
    return labels[priorite] || priorite;
};

const columns: Column[] = [
    {
        key: 'numero',
        title: 'N°',
    },
    {
        key: 'objet',
        title: 'OBJET',
        sortable: true,
    },
    {
        key: 'priorite',
        title: 'PRIORITÉ',
        sortable: true,
    },
    {
        key: 'date_debut',
        title: 'DATE DÉBUT',
        sortable: true,
    },
    {
        key: 'status',
        title: 'STATUT',
        sortable: true,
    },
    {
        key: 'current_step',
        title: 'ÉTAPE ACTUELLE',
    },
    {
        key: 'actions',
        title: 'ACTIONS',
    },
];

const tableData = computed(() => {
    return props.missions.data.map(mission => ({
        id: mission.id,
        numero: mission.numero_mission,
        objet: mission.objet,
        priorite: mission.priorite,
        date_debut: formatDateFr(mission.date_debut),
        status: mission.status,
        current_step: mission.etape_libelle ?? mission.current_step,
        mission: mission,
    }));
});

const handlePageChange = (page: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page.toString());
    
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    
    const newUrl = `/missions?${urlParams.toString()}`;
    
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['missions'],
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
        title: 'Mon tableau de bord',
        href: '#',
    },
];

const deleteDialogOpen = ref(false);
const missionToDelete = ref<Mission | null>(null);
const isDeleting = ref(false);

const openDeleteDialog = (mission: Mission) => {
    missionToDelete.value = mission;
    deleteDialogOpen.value = true;
};

const closeDeleteDialog = () => {
    if (isDeleting.value) return;
    deleteDialogOpen.value = false;
    missionToDelete.value = null;
};

const confirmDeleteMission = () => {
    if (!missionToDelete.value) return;

    isDeleting.value = true;
    router.delete(`/missions/${missionToDelete.value.id}`, {
        preserveScroll: true,
        headers: {
            'X-CSRF-TOKEN': csrfTokenForRequest(),
        },
        onFinish: () => {
            isDeleting.value = false;
            closeDeleteDialog();
        },
    });
};
</script>

<template>
    <Head title="Mon tableau de bord — Missions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="[missionPageBg, 'p-4 sm:p-6']">
        <div class="flex flex-col gap-4 sm:gap-6 max-w-[1600px] mx-auto">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-start gap-2">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Mon tableau de bord</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Vue d'ensemble de toutes vos missions — en cours, en validation, traitées ou clôturées.
                        </p>
                    </div>
                    <AlertCircle class="h-4 w-4 sm:h-5 sm:w-5 text-gray-500 mt-1 shrink-0" />
                </div>
                <Link href="/missions/create">
                    <Button class="gap-2">
                        <Plus class="h-4 w-4" />
                        Nouvelle mission
                    </Button>
                </Link>
            </div>

            <div v-if="props.statsMissions" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div :class="statCardSlate">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-600">Total missions</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.statsMissions.total }}</p>
                </div>
                <div :class="statCardSky">
                    <p class="text-xs font-medium uppercase tracking-wide text-sky-700/80">En cours</p>
                    <p class="mt-1 text-2xl font-bold text-blue-700">{{ props.statsMissions.en_cours }}</p>
                </div>
                <div :class="statCardEmerald">
                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700/80">Clôturées</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.statsMissions.cloturees }}</p>
                </div>
                <div :class="[statCardAmber, 'sm:col-span-2 lg:col-span-1']">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                        <BarChart3 class="h-3.5 w-3.5" /> Top 3 sites visités
                    </p>
                    <ul v-if="props.sitesPopulaires?.length" class="mt-2 space-y-1 text-sm">
                        <li v-for="item in props.sitesPopulaires" :key="item.site" class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1 truncate"><MapPin class="h-3.5 w-3.5 shrink-0 text-slate-500" />{{ item.site }}</span>
                            <span class="shrink-0 font-semibold text-slate-700">{{ item.count }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">Aucune donnée de site pour le moment.</p>
                </div>
            </div>

            <div :class="[missionCard, '!p-0 overflow-hidden']">
            <MissionDashboardSearch
                :filtre-numero="props.filtreNumero"
                :filtre-demandeur="props.filtreDemandeur"
            />
            <DataTable
                :columns="columns"
                :data="tableData"
                :total-items="totalItems"
                :current-page="currentPage"
                :per-page="perPage"
                :last-page="props.missions.last_page || 1"
                @page-change="handlePageChange"
                @items-per-page-change="handleItemsPerPageChange"
                @sort="handleSort"
            >
                <template #cell-numero="{ row }">
                    <span class="font-mono text-sm font-semibold text-slate-700 tabular-nums">{{ formatNumeroMission(row.mission.numero_mission) }}</span>
                </template>

                <template #cell-priorite="{ row }">
                    <span :class="`px-3 py-1 rounded-full text-xs font-medium ${getPriorityBadge(row.mission.priorite)}`">
                        {{ getPriorityLabel(row.mission.priorite) }}
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <span :class="`px-3 py-1 rounded-full text-xs font-medium ${getStatusBadge(row.mission.status)}`">
                        {{ getStatusLabel(row.mission.status) }}
                    </span>
                </template>

                <template #cell-current_step="{ row }">
                    <span class="text-sm text-slate-700">{{ row.mission.etape_libelle ?? row.mission.current_step }}</span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <Link :href="`/missions/${row.mission.id}`">
                            <Button variant="ghost" size="sm" class="gap-2" :title="estMissionnaireSeul(row.mission) ? 'Suivre l\'avancement' : 'Voir'">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link
                            v-if="peutModifier(row.mission)"
                            :href="`/missions/${row.mission.id}/edit`"
                        >
                            <Button variant="ghost" size="sm" class="gap-2">
                                <Pencil class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button
                            v-if="peutSupprimer(row.mission)"
                            variant="ghost"
                            size="sm"
                            class="gap-2 text-red-600 hover:text-red-700"
                            @click="openDeleteDialog(row.mission)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
            </div>

            <Dialog :open="deleteDialogOpen" @update:open="(open) => !open && closeDeleteDialog()">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Supprimer cette mission ?</DialogTitle>
                        <DialogDescription>
                            Vous êtes sur le point de supprimer la mission
                            <strong v-if="missionToDelete">« {{ missionToDelete.objet }} »</strong>.
                            Cette action est irréversible.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button variant="outline" :disabled="isDeleting" @click="closeDeleteDialog">
                            Annuler
                        </Button>
                        <Button variant="destructive" :disabled="isDeleting" @click="confirmDeleteMission">
                            {{ isDeleting ? 'Suppression…' : 'Supprimer' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
        </div>
    </AppLayout>
</template>
