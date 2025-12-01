<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Agence {
    id: number;
    nom: string;
    code_agent: string;
    description?: string;
    actif: boolean;
    profils_count?: number;
}

interface Props {
    agences: Agence[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agences',
        href: '#',
    },
];

const deleteAgence = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette agence ?')) {
        router.delete(`/agences/${id}`);
    }
};
</script>

<template>
    <Head title="Agences" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Gestion des agences</h1>
                <Link href="/agences/create">
                    <Button>Nouvelle agence</Button>
                </Link>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-muted">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium">Nom</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Code Agent</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Description</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Statut</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr v-for="agence in agences" :key="agence.id">
                                <td class="px-4 py-3 text-sm font-medium">{{ agence.nom }}</td>
                                <td class="px-4 py-3 text-sm font-mono">{{ agence.code_agent }}</td>
                                <td class="px-4 py-3 text-sm">{{ agence.description || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            agence.actif
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                        ]"
                                    >
                                        {{ agence.actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/agences/${agence.id}`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Voir
                                        </Link>
                                        <Link
                                            :href="`/agences/${agence.id}/edit`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Modifier
                                        </Link>
                                        <button
                                            @click="deleteAgence(agence.id)"
                                            class="text-destructive hover:underline text-sm"
                                        >
                                            Supprimer
                                        </button>
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

