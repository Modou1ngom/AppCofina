<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Role {
    id: number;
    nom: string;
    slug: string;
    description?: string;
    actif: boolean;
}

interface Props {
    roles: Role[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Rôles',
        href: '#',
    },
];

const deleteRole = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce rôle ?')) {
        router.delete(`/roles/${id}`);
    }
};
</script>

<template>
    <Head title="Rôles" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Gestion des rôles</h1>
                <div class="flex gap-2">
                    <Link href="/profils">
                        <Button variant="outline">Retour aux profils</Button>
                    </Link>
                    <Link href="/roles/create">
                        <Button>Nouveau rôle</Button>
                    </Link>
                </div>
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
                            <tr v-for="role in roles" :key="role.id">
                                <td class="px-4 py-3 text-sm font-medium">{{ role.nom }}</td>
                                <td class="px-4 py-3 text-sm">{{ role.description || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            role.actif
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                        ]"
                                    >
                                        {{ role.actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/roles/${role.id}`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Voir
                                        </Link>
                                        <Link
                                            :href="`/roles/${role.id}/edit`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Modifier
                                        </Link>
                                        <button
                                            @click="deleteRole(role.id)"
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

