<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { create, etape3 } from '@/routes/habilitations';
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

interface Props {
    habilitations: {
        data: Habilitation[];
        links: any;
        meta: any;
    };
}

defineProps<Props>();

const page = usePage();
const isControle = computed(() => page.props.auth?.isControle || false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
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
</script>

<template>
    <Head title="Demandes d'habilitation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Demandes d'habilitation</h1>
                <Link :href="create().url">
                    <Button>Nouvelle demande</Button>
                </Link>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-muted">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium">ID</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Type</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Demandeur</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Bénéficiaire</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Statut</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Date</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr v-for="habilitation in habilitations.data" :key="habilitation.id">
                                <td class="px-4 py-3 text-sm">#{{ habilitation.id }}</td>
                                <td class="px-4 py-3 text-sm">{{ habilitation.request_type }}</td>
                                <td class="px-4 py-3 text-sm">
                                    {{ habilitation.requester.prenom }} {{ habilitation.requester.nom }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ habilitation.beneficiary.prenom }} {{ habilitation.beneficiary.nom }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            getStatutBadge(habilitation.status),
                                        ]"
                                    >
                                        {{ getStatutLabel(habilitation.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ new Date(habilitation.created_at).toLocaleDateString('fr-FR') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/habilitations/${habilitation.id}`"
                                            class="text-primary hover:underline"
                                        >
                                            Voir
                                        </Link>
                                        <Link
                                            v-if="habilitation.status === 'pending_control' && isControle"
                                            :href="etape3.url({ habilitation: habilitation.id })"
                                        >
                                            <Button size="sm" variant="outline">
                                                Contrôler
                                            </Button>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


