<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { 
    FileCheck, 
    Users, 
    Layers, 
    Clock, 
    CheckCircle, 
    XCircle, 
    AlertCircle,
    TrendingUp,
    Activity,
    UserCheck,
    ShieldCheck,
    Server
} from 'lucide-vue-next';
import { computed } from 'vue';

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
}

interface ActionEnAttente {
    type: string;
    label: string;
    count: number;
    url: string;
}

interface Props {
    statsHabilitations: {
        total: number;
        draft: number;
        pending_n1: number;
        pending_n2: number;
        pending_control: number;
        approved: number;
        in_progress: number;
        completed: number;
        rejected: number;
    };
    statsProfils: {
        total: number;
        actifs: number;
        inactifs: number;
    };
    statsApplications: {
        total: number;
    };
    recentHabilitations: Habilitation[];
    actionsEnAttente: ActionEnAttente[];
    repartitionParType: {
        Creation: number;
        Modification: number;
        Desactivation: number;
        Suppression: number;
    };
    habilitationsParMois: Record<string, number>;
    userRole?: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tableau de matière',
        href: dashboard().url,
    },
];

const getStatutBadge = (status: string) => {
    const badges: Record<string, { class: string; label: string }> = {
        draft: { class: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200', label: 'Brouillon' },
        pending_n1: { class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', label: 'En attente N+1' },
        pending_n2: { class: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200', label: 'En attente N+2' },
        pending_control: { class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200', label: 'En attente Contrôle' },
        approved: { class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', label: 'Approuvée' },
        in_progress: { class: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200', label: 'En cours' },
        completed: { class: 'bg-green-200 text-green-900 dark:bg-green-800 dark:text-green-100', label: 'Terminée' },
        rejected: { class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', label: 'Rejetée' },
    };
    return badges[status] || { class: 'bg-gray-100 text-gray-800', label: status };
};

const getTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        Creation: 'Création',
        Modification: 'Modification',
        Desactivation: 'Désactivation',
        Suppression: 'Suppression',
    };
    return labels[type] || type;
};

const totalEnCours = computed(() => {
    return props.statsHabilitations.pending_n1 + 
           props.statsHabilitations.pending_n2 + 
           props.statsHabilitations.pending_control + 
           props.statsHabilitations.approved + 
           props.statsHabilitations.in_progress;
});

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric' 
    });
};

const dashboardTitle = computed(() => {
    const role = props.userRole || 'metier';
    const titles: Record<string, string> = {
        admin: 'Tableau de matière - Administrateur',
        rh: 'Tableau de matière - Ressources Humaines',
        controle: 'Tableau de matière - Contrôle Permanent',
        executeur_it: 'Tableau de matière - Exécuteur IT',
        metier: 'Tableau de matière',
    };
    return titles[role] || 'Tableau de matière';
});
</script>

<template>
    <Head title="Tableau de matière" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
            <div class="mb-4">
                <h1 class="text-2xl font-bold">{{ dashboardTitle }}</h1>
                <p class="text-sm text-muted-foreground mt-1">Vue d'ensemble des habilitations</p>
            </div>
            <!-- Actions en attente -->
            <div v-if="actionsEnAttente && actionsEnAttente.length > 0" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                <div class="flex items-center gap-2 mb-3">
                    <AlertCircle class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                    <h3 class="font-semibold text-yellow-900 dark:text-yellow-100">Actions en attente</h3>
                </div>
                <div class="space-y-2">
                    <Link
                        v-for="action in actionsEnAttente"
                        :key="action.type"
                        :href="action.url"
                        class="flex items-center justify-between rounded-md bg-white p-3 hover:bg-yellow-100 dark:bg-gray-800 dark:hover:bg-gray-700 transition-colors"
                    >
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ action.label }}</span>
                        <span class="rounded-full bg-yellow-500 px-3 py-1 text-xs font-semibold text-white">
                            {{ action.count }}
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Cartes de statistiques principales -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Habilitations -->
                <div class="rounded-lg border border-sidebar-border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Total Habilitations</p>
                            <p class="text-2xl font-bold">{{ statsHabilitations.total }}</p>
                        </div>
                        <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                            <FileCheck class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                </div>

                <!-- Habilitations en cours -->
                <div class="rounded-lg border border-sidebar-border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">En cours</p>
                            <p class="text-2xl font-bold">{{ totalEnCours }}</p>
                        </div>
                        <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                            <Clock class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                        </div>
                    </div>
                </div>

                <!-- Habilitations terminées -->
                <div class="rounded-lg border border-sidebar-border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Terminées</p>
                            <p class="text-2xl font-bold">{{ statsHabilitations.completed }}</p>
                        </div>
                        <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                            <CheckCircle class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                </div>

                <!-- Profils actifs -->
                <div class="rounded-lg border border-sidebar-border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Profils actifs</p>
                            <p class="text-2xl font-bold">{{ statsProfils.actifs }}</p>
                        </div>
                        <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                            <Users class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiques détaillées des habilitations -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-800">
                            <FileCheck class="h-4 w-4 text-gray-600 dark:text-gray-400" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Brouillons</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.draft }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-blue-100 p-2 dark:bg-blue-900">
                            <UserCheck class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">En attente N+1</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.pending_n1 }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-orange-100 p-2 dark:bg-orange-900">
                            <ShieldCheck class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">En attente N+2</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.pending_n2 }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-yellow-100 p-2 dark:bg-yellow-900">
                            <Activity class="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">En attente Contrôle</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.pending_control }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-green-100 p-2 dark:bg-green-900">
                            <CheckCircle class="h-4 w-4 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Approuvées</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.approved }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-purple-100 p-2 dark:bg-purple-900">
                            <Server class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">En cours IT</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.in_progress }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-green-200 p-2 dark:bg-green-800">
                            <CheckCircle class="h-4 w-4 text-green-700 dark:text-green-300" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Terminées</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.completed }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-4">
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-red-100 p-2 dark:bg-red-900">
                            <XCircle class="h-4 w-4 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Rejetées</p>
                            <p class="text-lg font-semibold">{{ statsHabilitations.rejected }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grille principale : Répartition et Habilitations récentes -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Répartition par type de demande -->
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h3 class="mb-4 text-lg font-semibold">Répartition par type de demande</h3>
                    <div class="space-y-3">
                        <div
                            v-for="(count, type) in repartitionParType"
                            :key="type"
                            class="flex items-center justify-between"
                        >
                            <span class="text-sm text-muted-foreground">{{ getTypeLabel(type) }}</span>
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-32 rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :style="{ width: `${statsHabilitations.total > 0 ? (count / statsHabilitations.total) * 100 : 0}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm font-semibold">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques des profils et applications -->
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h3 class="mb-4 text-lg font-semibold">Vue d'ensemble</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <div class="flex items-center gap-3">
                                <Users class="h-5 w-5 text-gray-100 dark:text-gray-100 "  />
                                <div>
                                    <p class="text-sm font-medium">Total Profils</p>
                                    <p class="text-xs text-muted-foreground">{{ statsProfils.actifs }} actifs, {{ statsProfils.inactifs }} inactifs</p>
                                </div>
                            </div>
                            <p class="text-xl font-bold">{{ statsProfils.total }}</p>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <div class="flex items-center gap-3">
                                <Layers class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                                <div>
                                    <p class="text-sm font-medium">Applications</p>
                                    <p class="text-xs text-muted-foreground">Applications actives</p>
                                </div>
                            </div>
                            <p class="text-xl font-bold">{{ statsApplications.total }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Habilitations récentes -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Habilitations récentes</h3>
                    <Link
                        href="/habilitations"
                        class="text-sm text-primary hover:underline"
                    >
                        Voir toutes →
                    </Link>
                </div>
                <div v-if="recentHabilitations && recentHabilitations.length > 0" class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-2 text-left text-sm font-medium text-muted-foreground">ID</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-muted-foreground">Demandeur</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-muted-foreground">Bénéficiaire</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-muted-foreground">Type</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-muted-foreground">Statut</th>
                                <th class="px-4 py-2 text-left text-sm font-medium text-muted-foreground">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="habilitation in recentHabilitations"
                                :key="habilitation.id"
                                class="border-b hover:bg-gray-50 dark:hover:bg-gray-800"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="`/habilitations/${habilitation.id}`"
                                        class="text-primary hover:underline"
                                    >
                                        #{{ habilitation.id }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    {{ habilitation.requester.prenom }} {{ habilitation.requester.nom }}
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    {{ habilitation.beneficiary.prenom }} {{ habilitation.beneficiary.nom }}
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    {{ getTypeLabel(habilitation.request_type) }}
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            getStatutBadge(habilitation.status).class
                                        ]"
                                    >
                                        {{ getStatutBadge(habilitation.status).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-muted-foreground">
                                    {{ formatDate(habilitation.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-8 text-center text-muted-foreground">
                    Aucune habilitation récente
                </div>
            </div>
        </div>
    </AppLayout>
</template>
