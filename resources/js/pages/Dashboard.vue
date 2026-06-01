<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { 
    FileCheck, 
    Users, 
    Clock, 
    CheckCircle, 
    AlertCircle,
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
        <div class="relative flex h-full flex-1 flex-col gap-6 overflow-x-auto bg-gradient-to-br from-gray-50 via-white to-gray-50 p-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="mb-3 flex items-center gap-3">
                    <div class="h-1 w-12 rounded-full bg-gradient-to-r from-blue-500 to-purple-500"></div>
                    <h1 class="text-4xl font-bold tracking-tight text-gray-900">
                        {{ dashboardTitle }}
                    </h1>
                </div>
                <p class="ml-15 text-base font-medium text-gray-600">Vue d'ensemble des habilitations et statistiques</p>
            </div>

            <!-- Actions en attente -->
            <div v-if="actionsEnAttente && actionsEnAttente.length > 0" class="mb-6 overflow-hidden rounded-xl border-2 border-yellow-200 bg-gradient-to-br from-yellow-50 to-orange-50 p-5 shadow-lg">
                <div class="mb-4 flex items-center gap-3">
                    <div class="rounded-full bg-gradient-to-br from-yellow-400 to-orange-400 p-2.5 shadow-md">
                        <AlertCircle class="h-5 w-5 text-white" />
                    </div>
                    <h3 class="text-lg font-bold text-yellow-900">Actions en attente</h3>
                </div>
                <div class="space-y-2.5">
                    <Link
                        v-for="action in actionsEnAttente"
                        :key="action.type"
                        :href="action.url"
                        class="group flex items-center justify-between rounded-lg border border-yellow-200 bg-white p-4 shadow-sm transition-all hover:border-yellow-300 hover:shadow-md"
                    >
                        <span class="text-sm font-medium text-gray-700 group-hover:text-yellow-800 transition-colors">{{ action.label }}</span>
                        <span class="rounded-full bg-gradient-to-r from-yellow-500 to-orange-500 px-4 py-1.5 text-xs font-bold text-white shadow-md group-hover:scale-105 transition-transform">
                            {{ action.count }}
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Cartes de statistiques principales -->
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Habilitations -->
                <div class="group relative overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-blue-100 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-xl">
                    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-blue-200/50 blur-2xl"></div>
                    <div class="relative flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-blue-700">Total Habilitations</p>
                            <p class="text-3xl font-bold text-blue-900">{{ statsHabilitations.total }}</p>
                        </div>
                        <div class="rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-4 shadow-lg group-hover:scale-110 transition-transform">
                            <FileCheck class="h-7 w-7 text-white" />
                        </div>
                    </div>
                </div>

                <!-- Habilitations en cours -->
                <div class="group relative overflow-hidden rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-orange-100 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-xl">
                    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-orange-200/50 blur-2xl"></div>
                    <div class="relative flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-orange-700">En cours</p>
                            <p class="text-3xl font-bold text-orange-900">{{ totalEnCours }}</p>
                        </div>
                        <div class="rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 p-4 shadow-lg group-hover:scale-110 transition-transform">
                            <Clock class="h-7 w-7 text-white" />
                        </div>
                    </div>
                </div>

                <!-- Habilitations terminées -->
                <div class="group relative overflow-hidden rounded-xl border border-green-200 bg-gradient-to-br from-green-50 to-green-100 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-xl">
                    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-green-200/50 blur-2xl"></div>
                    <div class="relative flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-green-700">Terminées</p>
                            <p class="text-3xl font-bold text-green-900">{{ statsHabilitations.completed }}</p>
                        </div>
                        <div class="rounded-xl bg-gradient-to-br from-green-500 to-green-600 p-4 shadow-lg group-hover:scale-110 transition-transform">
                            <CheckCircle class="h-7 w-7 text-white" />
                        </div>
                    </div>
                </div>

                <!-- Profils actifs -->
                <div class="group relative overflow-hidden rounded-xl border border-purple-200 bg-gradient-to-br from-purple-50 to-purple-100 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-xl">
                    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-purple-200/50 blur-2xl"></div>
                    <div class="relative flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-purple-700">Profils actifs</p>
                            <p class="text-3xl font-bold text-purple-900">{{ statsProfils.actifs }}</p>
                        </div>
                        <div class="rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 p-4 shadow-lg group-hover:scale-110 transition-transform">
                            <Users class="h-7 w-7 text-white" />
                        </div>
                    </div>
                </div>
            </div>



            <!-- Habilitations récentes -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-lg">
                <div class="mb-5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-1 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-500"></div>
                        <h3 class="text-xl font-bold text-gray-900">Habilitations récentes</h3>
                    </div>
                    <Link
                        href="/habilitations"
                        class="group flex items-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition-all hover:scale-105 hover:shadow-lg"
                    >
                        Voir toutes
                        <span class="transition-transform group-hover:translate-x-1">→</span>
                    </Link>
                </div>
                <div v-if="recentHabilitations && recentHabilitations.length > 0" class="space-y-3">
                    <Link
                        v-for="habilitation in recentHabilitations.slice(0, 5)"
                        :key="habilitation.id"
                        :href="`/habilitations/${habilitation.id}`"
                        class="group flex items-center justify-between rounded-lg border-2 border-gray-100 bg-gradient-to-r from-white to-gray-50 p-5 shadow-sm transition-all hover:border-blue-200 hover:shadow-md"
                    >
                        <div class="flex items-center gap-4">
                            <div class="relative">
                                <div class="absolute inset-0 rounded-xl bg-blue-500 blur-md opacity-30 group-hover:opacity-50 transition-opacity"></div>
                                <div class="relative flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-bold text-white shadow-lg group-hover:scale-110 transition-transform">
                                    #{{ habilitation.id }}
                                </div>
                            </div>
                            <div>
                                <p class="mb-1 font-semibold text-gray-900">
                                    {{ habilitation.beneficiary.prenom }} {{ habilitation.beneficiary.nom }}
                                </p>
                                <p class="text-sm font-medium text-gray-500">
                                    Par {{ habilitation.requester.prenom }} {{ habilitation.requester.nom }}
                                </p>
                            </div>
                        </div>
                        <span
                            :class="[
                                'rounded-full px-4 py-2 text-xs font-bold shadow-sm',
                                getStatutBadge(habilitation.status).class
                            ]"
                        >
                            {{ getStatutBadge(habilitation.status).label }}
                        </span>
                    </Link>
                </div>
                <div v-else class="py-16 text-center">
                    <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-gray-100 p-4">
                        <FileCheck class="h-8 w-8 text-gray-400" />
                    </div>
                    <p class="font-medium text-gray-500">Aucune habilitation récente</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
