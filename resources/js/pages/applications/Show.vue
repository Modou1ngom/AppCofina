<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Application {
    id: number;
    nom: string;
    description?: string;
    actif: boolean;
    ordre: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    application: Application;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Applications',
        href: '/applications',
    },
    {
        title: props.application.nom,
        href: '#',
    },
];
</script>

<template>
    <Head :title="`Application: ${props.application.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ props.application.nom }}</h1>
                <div class="flex gap-2">
                    <Link :href="`/applications/${props.application.id}/edit`">
                        <Button variant="outline">Modifier</Button>
                    </Link>
                    <Link href="/applications">
                        <Button variant="outline">Retour à la liste</Button>
                    </Link>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <div class="flex flex-col gap-4">
                    <div>
                        <h2 class="text-sm font-medium text-muted-foreground mb-1">Nom</h2>
                        <p class="text-lg font-semibold">{{ props.application.nom }}</p>
                    </div>

                    <div v-if="props.application.description">
                        <h2 class="text-sm font-medium text-muted-foreground mb-1">Description</h2>
                        <p class="text-sm">{{ props.application.description }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <h2 class="text-sm font-medium text-muted-foreground mb-1">Statut</h2>
                            <span
                                :class="[
                                    'inline-flex px-2 py-1 text-xs font-medium rounded-full',
                                    props.application.actif
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]"
                            >
                                {{ props.application.actif ? 'Actif' : 'Inactif' }}
                            </span>
                        </div>

                        <div>
                            <h2 class="text-sm font-medium text-muted-foreground mb-1">Ordre d'affichage</h2>
                            <p class="text-sm">{{ props.application.ordre }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <h2 class="text-sm font-medium text-muted-foreground mb-1">Date de création</h2>
                            <p class="text-sm">{{ new Date(props.application.created_at).toLocaleDateString('fr-FR', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            }) }}</p>
                        </div>

                        <div>
                            <h2 class="text-sm font-medium text-muted-foreground mb-1">Dernière modification</h2>
                            <p class="text-sm">{{ new Date(props.application.updated_at).toLocaleDateString('fr-FR', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            }) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

