<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Profil {
    id: number;
    prenom: string;
    nom: string;
    matricule: string;
}

interface Props {
    agence: {
        id: number;
        nom: string;
        code_agent: string;
        description?: string;
        actif: boolean;
        profils?: Profil[];
        chef_agence?: Profil;
    };
    profils: Profil[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agences',
        href: '/agences',
    },
    {
        title: props.agence.nom,
        href: '#',
    },
];
</script>

<template>
    <Head :title="agence.nom" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ agence.nom }}</h1>
                <div class="flex gap-2">
                    <Link :href="`/agences/${agence.id}/edit`">
                        <Button variant="outline">Modifier</Button>
                    </Link>
                    <Link href="/agences">
                        <Button>Retour à la liste</Button>
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations de l'agence</h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Nom</dt>
                            <dd class="mt-1 text-sm">{{ agence.nom }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Code Agent</dt>
                            <dd class="mt-1 text-sm font-mono">{{ agence.code_agent }}</dd>
                        </div>
                        <div v-if="agence.description">
                            <dt class="text-muted-foreground text-sm font-medium">Description</dt>
                            <dd class="mt-1 text-sm">{{ agence.description }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Statut</dt>
                            <dd class="mt-1">
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
                            </dd>
                        </div>
                        <div v-if="agence.chef_agence">
                            <dt class="text-muted-foreground text-sm font-medium">Chef d'agence</dt>
                            <dd class="mt-1 text-sm">
                                <Link
                                    :href="`/profils/${agence.chef_agence.id}`"
                                    class="text-primary hover:underline"
                                >
                                    {{ agence.chef_agence.prenom }} {{ agence.chef_agence.nom }} ({{ agence.chef_agence.matricule }})
                                </Link>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Profils de cette agence</h2>
                    <div v-if="props.profils && props.profils.length > 0" class="space-y-2">
                        <div
                            v-for="profil in props.profils"
                            :key="profil.id"
                            class="text-sm"
                        >
                            <Link
                                :href="`/profils/${profil.id}`"
                                class="text-primary hover:underline"
                            >
                                {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                            </Link>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">Aucun profil dans cette agence</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

