<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/habilitations';

interface Habilitation {
    id: number;
    statut: string;
    type_demande: string;
    demandeur: {
        nom: string;
        prenom: string;
    };
    beneficiaire: {
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
        href: '#',
    },
];

const getStatutBadge = (statut: string) => {
    const badges: Record<string, string> = {
        brouillon: 'bg-gray-100 text-gray-800',
        en_attente_n1: 'bg-blue-100 text-blue-800',
        en_attente_controle: 'bg-yellow-100 text-yellow-800',
        en_attente_n2: 'bg-orange-100 text-orange-800',
        approuvee: 'bg-green-100 text-green-800',
        rejetee: 'bg-red-100 text-red-800',
        en_cours_execution: 'bg-purple-100 text-purple-800',
        terminee: 'bg-green-200 text-green-900',
    };
    return badges[statut] || 'bg-gray-100 text-gray-800';
};

const getStatutLabel = (statut: string) => {
    const labels: Record<string, string> = {
        brouillon: 'Brouillon',
        en_attente_n1: 'En attente N+1',
        en_attente_controle: 'En attente Contrôle',
        en_attente_n2: 'En attente N+2',
        approuvee: 'Approuvée',
        rejetee: 'Rejetée',
        en_cours_execution: 'En cours d\'exécution',
        terminee: 'Terminée',
    };
    return labels[statut] || statut;
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
                                <td class="px-4 py-3 text-sm">{{ habilitation.type_demande }}</td>
                                <td class="px-4 py-3 text-sm">
                                    {{ habilitation.demandeur.prenom }} {{ habilitation.demandeur.nom }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ habilitation.beneficiaire.prenom }} {{ habilitation.beneficiaire.nom }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            getStatutBadge(habilitation.statut),
                                        ]"
                                    >
                                        {{ getStatutLabel(habilitation.statut) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ new Date(habilitation.created_at).toLocaleDateString('fr-FR') }}
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/habilitations/${habilitation.id}`"
                                        class="text-primary hover:underline"
                                    >
                                        Voir
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


