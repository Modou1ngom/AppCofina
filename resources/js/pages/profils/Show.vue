<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Props {
    profil: {
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
        n_plus1?: {
            id: number;
            prenom: string;
            nom: string;
            matricule: string;
        };
        n_plus2?: {
            id: number;
            prenom: string;
            nom: string;
            matricule: string;
        };
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profils',
        href: '/profils',
    },
    {
        title: `${props.profil.prenom} ${props.profil.nom}`,
        href: '#',
    },
];
</script>

<template>
    <Head :title="`${profil.prenom} ${profil.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ profil.prenom }} {{ profil.nom }}</h1>
                <div class="flex gap-2">
                    <Link :href="`/profils/${profil.id}/edit`">
                        <Button variant="outline">Modifier</Button>
                    </Link>
                    <Link href="/profils">
                        <Button>Retour à la liste</Button>
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations personnelles</h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Matricule</dt>
                            <dd class="mt-1 text-sm">{{ profil.matricule }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Prénom</dt>
                            <dd class="mt-1 text-sm">{{ profil.prenom }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Nom</dt>
                            <dd class="mt-1 text-sm">{{ profil.nom }}</dd>
                        </div>
                        <div v-if="profil.email">
                            <dt class="text-muted-foreground text-sm font-medium">Email</dt>
                            <dd class="mt-1 text-sm">{{ profil.email }}</dd>
                        </div>
                        <div v-if="profil.telephone">
                            <dt class="text-muted-foreground text-sm font-medium">Téléphone</dt>
                            <dd class="mt-1 text-sm">{{ profil.telephone }}</dd>
                        </div>
                        <div v-if="profil.site">
                            <dt class="text-muted-foreground text-sm font-medium">Site</dt>
                            <dd class="mt-1 text-sm">{{ profil.site }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations professionnelles</h2>
                    <dl class="space-y-3">
                        <div v-if="profil.fonction">
                            <dt class="text-muted-foreground text-sm font-medium">Fonction</dt>
                            <dd class="mt-1 text-sm">{{ profil.fonction }}</dd>
                        </div>
                        <div v-if="profil.departement">
                            <dt class="text-muted-foreground text-sm font-medium">Département</dt>
                            <dd class="mt-1 text-sm">{{ profil.departement }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Type de contrat</dt>
                            <dd class="mt-1 text-sm">{{ profil.type_contrat }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Statut</dt>
                            <dd class="mt-1">
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
                            </dd>
                        </div>
                        <div v-if="profil.n_plus1">
                            <dt class="text-muted-foreground text-sm font-medium">N+1</dt>
                            <dd class="mt-1 text-sm">
                                <Link
                                    :href="`/profils/${profil.n_plus1.id}`"
                                    class="text-primary hover:underline"
                                >
                                    {{ profil.n_plus1.prenom }} {{ profil.n_plus1.nom }}
                                    ({{ profil.n_plus1.matricule }})
                                </Link>
                            </dd>
                        </div>
                        <div v-if="profil.n_plus2">
                            <dt class="text-muted-foreground text-sm font-medium">N+2</dt>
                            <dd class="mt-1 text-sm">
                                <Link
                                    :href="`/profils/${profil.n_plus2.id}`"
                                    class="text-primary hover:underline"
                                >
                                    {{ profil.n_plus2.prenom }} {{ profil.n_plus2.nom }}
                                    ({{ profil.n_plus2.matricule }})
                                </Link>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

