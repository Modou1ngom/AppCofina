<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { create, etape3, etape4, etape5, etape6 } from '@/routes/habilitations';
import { computed, ref, watch } from 'vue';
import { useBeneficiaryDialog } from '@/composables/useBeneficiaryDialog';

interface Habilitation {
    id: number;
    status: string;
    request_type: string;
    requester: {
        nom: string;
        prenom: string;
    };
    beneficiary: {
        nom: string;
        prenom: string;
        n_plus_1_id?: number | null;
        n_plus_2_id?: number | null;
    };
    created_at: string;
}

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
    fonction?: string;
    departement?: string;
    email?: string;
    telephone?: string;
    site?: string;
}

interface Props {
    habilitations: {
        data: Habilitation[];
        links: any;
        meta: any;
    };
    filter?: string;
    subordonnes?: Profil[];
    hasDepartement?: boolean;
}

const props = defineProps<Props>();

const page = usePage();
const { openDialog, isOpen } = useBeneficiaryDialog();

// Créer un computed pour la réactivité
const isDialogOpen = computed(() => isOpen.value);

// Ouvrir automatiquement le dialog si on vient du menu avec openDialog=true
watch(() => page.url, (url) => {
    if (url.includes('openDialog=true') && props.hasDepartement && props.subordonnes && props.subordonnes.length > 0) {
        openDialog(props.subordonnes);
    }
}, { immediate: true });

const isControle = computed(() => (page.props.auth as any)?.isControle || false);
const isMetier = computed(() => (page.props.auth as any)?.isMetier || false);
const isAdmin = computed(() => (page.props.auth as any)?.isAdmin || false);
const currentProfil = computed(() => (page.props.auth as any)?.profil || null);

// Fonction pour vérifier si l'utilisateur peut valider N+1 (admin peut voir mais pas valider)
const canValidateN1 = (habilitation: Habilitation) => {
    if (habilitation.status !== 'pending_n1') return false;
    if (!currentProfil.value || !habilitation.beneficiary) return false;
    return habilitation.beneficiary.n_plus_1_id === currentProfil.value.id;
};

// Fonction pour vérifier si l'utilisateur peut valider N+2 (admin peut voir mais pas valider)
const canValidateN2 = (habilitation: Habilitation) => {
    if (habilitation.status !== 'pending_n2') return false;
    if (!currentProfil.value || !habilitation.beneficiary) return false;
    return habilitation.beneficiary.n_plus_2_id === currentProfil.value.id;
};

// Fonction pour vérifier si l'utilisateur peut valider le Contrôle (admin peut voir mais pas valider)
const canValidateControl = (habilitation: Habilitation) => {
    return isControle.value && habilitation.status === 'pending_control';
};

// Fonction pour vérifier si l'utilisateur peut exécuter IT (uniquement admin)
const canExecuteIT = (habilitation: Habilitation) => {
    return isAdmin.value && (habilitation.status === 'approved' || habilitation.status === 'in_progress');
};

const filterLabel = computed(() => {
    const filter = props.filter || page.url.split('filter=')[1]?.split('&')[0] || 'all';
    const labels: Record<string, string> = {
        all: 'Toutes les habilitations',
        encours: 'Habilitations en cours',
        termine: 'Habilitations terminées',
        rejete: 'Habilitations rejetées',
    };
    return labels[filter] || 'Toutes les habilitations';
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: filterLabel.value,
        href: '#',
    },
];

const getStatutBadge = (status: string) => {
    const badges: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        pending_n1: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        pending_control: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        pending_n2: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        approved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        in_progress: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        completed: 'bg-green-200 text-green-900 dark:bg-green-800 dark:text-green-100',
    };
    return badges[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
};

const getStatutLabel = (status: string) => {
    const labels: Record<string, string> = {
        draft: 'Brouillon',
        pending_n1: 'En attente N+1',
        pending_control: 'En attente Contrôle',
        pending_n2: 'En attente N+2',
        approved: 'Approuvée',
        rejected: 'Rejetée',
        in_progress: 'En cours d\'exécution',
        completed: 'Terminée',
    };
    return labels[status] || status;
};

</script>

<template>
    <Head title="Demandes d'habilitation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Contenu de la page (masqué si le dialog est ouvert) -->
        <div v-if="!isDialogOpen" class="flex flex-col gap-6 p-6">
               
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
                                <td class="px-4 py-3 text-sm">{{ habilitation.request_type }}</td>
                                <td class="px-4 py-3 text-sm">
                                    {{ habilitation.requester.prenom }} {{ habilitation.requester.nom }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ habilitation.beneficiary.prenom }} {{ habilitation.beneficiary.nom }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            getStatutBadge(habilitation.status),
                                        ]"
                                    >
                                        {{ getStatutLabel(habilitation.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ new Date(habilitation.created_at).toLocaleDateString('fr-FR') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/habilitations/${habilitation.id}`"
                                            class="text-primary hover:underline"
                                        >
                                            Voir
                                        </Link>
                                        <Link
                                            v-if="canValidateN1(habilitation)"
                                            :href="etape3.url({ habilitation: habilitation.id })"
                                        >
                                            <Button size="sm" variant="outline">
                                                Valider N+1
                                            </Button>
                                        </Link>
                                        <Link
                                            v-if="canValidateN2(habilitation)"
                                            :href="etape4.url({ habilitation: habilitation.id })"
                                        >
                                            <Button size="sm" variant="outline">
                                                Valider N+2
                                            </Button>
                                        </Link>
                                        <Link
                                            v-if="canValidateControl(habilitation)"
                                            :href="etape5.url({ habilitation: habilitation.id })"
                                        >
                                            <Button size="sm" variant="outline">
                                                Contrôler
                                            </Button>
                                        </Link>
                                        <Link
                                            v-if="canExecuteIT(habilitation)"
                                            :href="etape6.url({ habilitation: habilitation.id })"
                                        >
                                            <Button size="sm" variant="outline">
                                                Exécuter IT
                                            </Button>
                                        </Link>
                                        <Link
                                            v-if="habilitation.status === 'pending_control' && isControle"
                                            :href="etape5.url({ habilitation: habilitation.id })"
                                        >
                                            <Button size="sm" variant="outline">
                                                Contrôler
                                            </Button>
                                        </Link>
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


