<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Departement {
    id: number;
    nom: string;
    description?: string;
    actif: boolean;
}

interface Props {
    departements: Departement[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Départements',
        href: '#',
    },
];

const deleteDepartement = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce département ?')) {
        router.delete(`/departements/${id}`);
    }
};
</script>

<template>
    <Head title="Départements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Gestion des départements</h1>
                <Link href="/departements/create">
                    <Button>Nouveau département</Button>
                </Link>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-muted">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium">Nom</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Description</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Statut</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr v-for="departement in departements" :key="departement.id">
                                <td class="px-4 py-3 text-sm font-medium">{{ departement.nom }}</td>
                                <td class="px-4 py-3 text-sm">{{ departement.description || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            departement.actif
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                        ]"
                                    >
                                        {{ departement.actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/departements/${departement.id}`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Voir
                                        </Link>
                                        <Link
                                            :href="`/departements/${departement.id}/edit`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Modifier
                                        </Link>
                                        <button
                                            @click="deleteDepartement(departement.id)"
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

