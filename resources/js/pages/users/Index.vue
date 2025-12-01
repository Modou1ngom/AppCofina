<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    profil?: {
        id: number;
        nom: string;
        prenom: string;
        matricule: string;
    };
}

interface Props {
    users: {
        data: User[];
        links: any[];
        meta: any;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: '#',
    },
];

const deleteUser = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
        router.delete(`/users/${id}`);
    }
};

const toggleUser = (id: number) => {
    router.post(`/users/${id}/toggle`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Utilisateurs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Gestion des utilisateurs</h1>
                <Link href="/users/create">
                    <Button>Nouvel utilisateur</Button>
                </Link>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-muted">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium">Nom</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Email</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Profil associé</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Statut</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Date de création</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr v-for="user in props.users.data" :key="user.id">
                                <td class="px-4 py-3 text-sm font-medium">{{ user.name }}</td>
                                <td class="px-4 py-3 text-sm">{{ user.email }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span v-if="user.profil" class="text-primary">
                                        {{ user.profil.prenom }} {{ user.profil.nom }} ({{ user.profil.matricule }})
                                    </span>
                                    <span v-else class="text-muted-foreground">Aucun profil</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <button
                                        @click="toggleUser(user.id)"
                                        :class="[
                                            'px-2 py-1 text-xs font-medium rounded-full transition-colors',
                                            user.is_active
                                                ? 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400'
                                        ]"
                                    >
                                        {{ user.is_active ? 'Actif' : 'Inactif' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ new Date(user.created_at).toLocaleDateString('fr-FR') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/users/${user.id}`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Voir
                                        </Link>
                                        <Link
                                            :href="`/users/${user.id}/edit`"
                                            class="text-primary hover:underline text-sm"
                                        >
                                            Modifier
                                        </Link>
                                        <button
                                            @click="deleteUser(user.id)"
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

                <!-- Pagination -->
                <div v-if="props.users.links.length > 3" class="flex items-center justify-between border-t border-sidebar-border px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Link
                            v-for="link in props.users.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-3 py-1 text-sm rounded',
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-background text-foreground hover:bg-muted',
                                !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

