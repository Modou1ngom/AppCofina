<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Role {
    id: number;
    nom: string;
    slug: string;
}

interface Profil {
    id: number;
    matricule: string;
    prenom: string;
    nom: string;
    fonction?: string;
    departement?: string;
    email?: string;
    telephone?: string;
    site?: string;
    type_contrat: string;
    statut: string;
    roles?: Role[];
}

interface Props {
    profils: Profil[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profils',
        href: '#',
    },
];

const deleteProfil = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce profil ?')) {
        router.delete(`/profils/${id}`);
    }
};
</script>

<template>
    <Head title="Profils" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Gestion des profils</h1>
                <div class="flex gap-2">
                    <Link href="/roles">
                        <Button variant="outline">Gérer les rôles</Button>
                    </Link>
                    <Link href="/profils/create">
                        <Button>Nouveau profil</Button>
                    </Link>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-muted">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium">Matricule</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Nom complet</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Fonction</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Département</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Rôles</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Statut</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr v-for="profil in profils" :key="profil.id">
                                <td class="px-4 py-3 text-sm font-medium">{{ profil.matricule }}</td>
                                <td class="px-4 py-3 text-sm">
                                    {{ profil.prenom }} {{ profil.nom }}
                                </td>
                                <td class="px-4 py-3 text-sm">{{ profil.fonction || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ profil.departement || '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="role in profil.roles"
                                            :key="role.id"
                                            class="rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                                        >
                                            {{ role.nom }}
                                        </span>
                                        <span v-if="!profil.roles || profil.roles.length === 0" class="text-muted-foreground text-xs">
                                            Aucun rôle
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            profil.statut === 'actif'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                        ]"
                                    >
                                        {{ profil.statut === 'actif' ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/profils/${profil.id}`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Voir
                                        </Link>
                                        <Link
                                            :href="`/profils/${profil.id}/edit`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Modifier
                                        </Link>
                                        <button
                                            @click="deleteProfil(profil.id)"
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

