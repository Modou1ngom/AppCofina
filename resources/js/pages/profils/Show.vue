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
        email?: string | null;
        telephone?: string;
        compte_utilisateur?: {
            id: number;
            email: string;
            name: string;
        } | null;
        site?: string;
        type_contrat: string;
        statut: string;
        pointage_statut?: string | null;
        pointage_statut_label?: string | null;
        date_entree?: string | null;
        date_sortie?: string | null;
        motif_depart?: string | null;
        n_plus_1?: {
            id: number;
            prenom: string;
            nom: string;
            matricule: string;
        };
        n_plus_2?: {
            id: number;
            prenom: string;
            nom: string;
            matricule: string;
        };
        subordonnes?: Array<{
            id: number;
            prenom: string;
            nom: string;
            matricule: string;
        }>;
        mouvements?: Array<{
            id: number;
            type: string;
            type_label: string;
            date_effet: string | null;
            motif: string | null;
            fonction_avant: string | null;
            fonction_apres: string | null;
            departement_avant: string | null;
            departement_apres: string | null;
            site_avant: string | null;
            site_apres: string | null;
            createur?: string | null;
        }>;
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
                    <Link
                        v-if="profil.statut === 'actif'"
                        :href="`/profils/mouvements/changement-poste?profil_id=${profil.id}`"
                    >
                        <Button variant="outline">Changement de poste</Button>
                    </Link>
                    <Link
                        v-if="profil.statut === 'actif'"
                        :href="`/profils/mouvements/depart?profil_id=${profil.id}`"
                    >
                        <Button variant="outline" class="border-red-300 text-red-700 hover:bg-red-50">Déclarer un départ</Button>
                    </Link>
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
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Email</dt>
                            <dd class="mt-1 text-sm">
                                <a
                                    v-if="profil.email"
                                    :href="`mailto:${profil.email}`"
                                    class="text-primary hover:underline"
                                >{{ profil.email }}</a>
                                <span v-else class="text-muted-foreground italic">Non renseigné</span>
                            </dd>
                        </div>
                        <div v-if="profil.compte_utilisateur">
                            <dt class="text-muted-foreground text-sm font-medium">Compte utilisateur</dt>
                            <dd class="mt-1 text-sm">
                                {{ profil.compte_utilisateur.name }}
                                <span class="text-muted-foreground">({{ profil.compte_utilisateur.email }})</span>
                            </dd>
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
                            <dd class="mt-1 text-sm">{{ profil.type_contrat || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Statut</dt>
                            <dd class="mt-1">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-1 text-xs font-medium',
                                        profil.pointage_statut === 'en_attente'
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
                                            : profil.statut === 'actif'
                                              ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                              : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                    ]"
                                >
                                    {{
                                        profil.pointage_statut === 'en_attente'
                                            ? 'En attente de pointage'
                                            : profil.statut === 'actif'
                                              ? 'Actif'
                                              : 'Inactif'
                                    }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="profil.pointage_statut_label">
                            <dt class="text-muted-foreground text-sm font-medium">Pointage</dt>
                            <dd class="mt-1 text-sm">{{ profil.pointage_statut_label }}</dd>
                        </div>
                        <div v-if="profil.date_entree">
                            <dt class="text-muted-foreground text-sm font-medium">Date d'arrivée</dt>
                            <dd class="mt-1 text-sm">{{ profil.date_entree }}</dd>
                        </div>
                        <div v-if="profil.date_sortie">
                            <dt class="text-muted-foreground text-sm font-medium">Date de départ</dt>
                            <dd class="mt-1 text-sm">{{ profil.date_sortie }}</dd>
                        </div>
                        <div v-if="profil.motif_depart">
                            <dt class="text-muted-foreground text-sm font-medium">Motif de départ</dt>
                            <dd class="mt-1 text-sm">{{ profil.motif_depart }}</dd>
                        </div>
                        <div v-if="profil.n_plus_1">
                            <dt class="text-muted-foreground text-sm font-medium">N+1</dt>
                            <dd class="mt-1 text-sm">
                                {{ profil.n_plus_1.prenom }} {{ profil.n_plus_1.nom }}
                                ({{ profil.n_plus_1.matricule }})
                            </dd>
                        </div>
                        <div v-if="profil.n_plus_2">
                            <dt class="text-muted-foreground text-sm font-medium">N+2</dt>
                            <dd class="mt-1 text-sm">
                                {{ profil.n_plus_2.prenom }} {{ profil.n_plus_2.nom }}
                                ({{ profil.n_plus_2.matricule }})
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-if="profil.subordonnes && profil.subordonnes.length > 0" class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Subordonnés</h2>
                    <ul class="space-y-2">
                        <li
                            v-for="subordonne in profil.subordonnes"
                            :key="subordonne.id"
                            class="text-sm"
                        >
                            <Link
                                :href="`/profils/${subordonne.id}`"
                                class="text-primary hover:underline"
                            >
                                {{ subordonne.prenom }} {{ subordonne.nom }} ({{ subordonne.matricule }})
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                v-if="profil.mouvements && profil.mouvements.length > 0"
                class="rounded-lg border border-sidebar-border bg-card p-6"
            >
                <h2 class="mb-4 text-lg font-semibold">Historique des mouvements</h2>
                <ul class="space-y-3">
                    <li
                        v-for="mouvement in profil.mouvements"
                        :key="mouvement.id"
                        class="rounded-md border border-gray-100 bg-gray-50 p-3 text-sm"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium text-gray-900">{{ mouvement.type_label }}</span>
                            <span class="text-gray-500">{{ mouvement.date_effet || '—' }}</span>
                        </div>
                        <p v-if="mouvement.fonction_avant !== mouvement.fonction_apres" class="mt-1 text-gray-700">
                            Poste : {{ mouvement.fonction_avant || '—' }} → {{ mouvement.fonction_apres || '—' }}
                        </p>
                        <p v-if="mouvement.departement_avant !== mouvement.departement_apres" class="text-gray-700">
                            Département : {{ mouvement.departement_avant || '—' }} → {{ mouvement.departement_apres || '—' }}
                        </p>
                        <p v-if="mouvement.motif" class="mt-1 text-gray-600">{{ mouvement.motif }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

