<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { etape2, etape3, etape4, etape5, etape6, show } from '@/routes/habilitations';
import { computed } from 'vue';
import { Printer } from 'lucide-vue-next';

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Habilitation {
    id: number;
    requester: Profil;
    beneficiary: Profil & {
        n_plus_1_id?: number | null;
        n_plus_2_id?: number | null;
        n_plus_1?: Profil;
        n_plus_2?: Profil;
    };
    request_type: string;
    requested_profile?: string;
    profile_type?: string;
    specific_profile?: string;
    applications?: string[];
    other_application?: string;
    comment_n1?: string;
    comment_control?: string;
    comment_n2?: string;
    comment_it?: string;
    status: string;
    validator_n1?: User;
    validator_control?: User;
    validator_n2?: User;
    executor_it?: User;
    validated_n1_at?: string;
    validated_control_at?: string;
    validated_n2_at?: string;
    executed_it_at?: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    habilitation: Habilitation;
}

const props = defineProps<Props>();

// Accès aux données d'authentification depuis Inertia
const page = usePage();
const isAdmin = computed(() => (page.props.auth as any)?.isAdmin || false);
const isMetier = computed(() => (page.props.auth as any)?.isMetier || false);
const isControle = computed(() => (page.props.auth as any)?.isControle || false);
const currentProfil = computed(() => (page.props.auth as any)?.profil || null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
        href: '/habilitations',
    },
    {
        title: `Demande #${props.habilitation.id}`,
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
    return badges[status] || 'bg-gray-100 text-gray-800';
};

const getStatutLabel = (status: string) => {
    const labels: Record<string, string> = {
        draft: 'Brouillon',
        pending_n1: 'En attente N+1',
        pending_control: 'En attente Contrôle Permanent',
        pending_n2: 'En attente N+2',
        approved: 'Approuvée',
        rejected: 'Rejetée',
        in_progress: 'En cours d\'exécution',
        completed: 'Terminée',
    };
    return labels[status] || status;
};

// Admin peut voir toutes les étapes, les autres utilisateurs selon le statut
// Étape 2 : Définir les droits - accessible uniquement si le statut est 'draft'
const canAccessEtape2 = computed(() => 
    isAdmin.value || props.habilitation.status === 'draft'
);

// Étape 3 : Validation N+1 - accessible uniquement si l'utilisateur est le N+1 du bénéficiaire
// L'admin ne voit ce bouton que s'il est lui-même le N+1 du bénéficiaire
const canAccessEtape3 = computed(() => {
    if (props.habilitation.status !== 'pending_n1') return false;
    if (!currentProfil.value || !props.habilitation.beneficiary) return false;
    return props.habilitation.beneficiary.n_plus_1_id === currentProfil.value.id;
});

// Étape 4 : Validation N+2 - accessible uniquement si l'utilisateur est le N+2 du bénéficiaire
// L'admin ne voit ce bouton que s'il est lui-même le N+2 du bénéficiaire
const canAccessEtape4 = computed(() => {
    if (props.habilitation.status !== 'pending_n2') return false;
    if (!currentProfil.value || !props.habilitation.beneficiary) return false;
    return props.habilitation.beneficiary.n_plus_2_id === currentProfil.value.id;
});

// Étape 5 : Validation Contrôle - accessible uniquement si l'utilisateur a le rôle Contrôle
// ET que le N+1 et le N+2 ont validé
const canAccessEtape5 = computed(() => {
    if (!isControle.value || props.habilitation.status !== 'pending_control') {
        return false;
    }
    // Vérifier que le N+1 a validé
    if (!props.habilitation.validator_n1 || !props.habilitation.validated_n1_at) {
        return false;
    }
    // Vérifier que le N+2 a validé
    if (!props.habilitation.validator_n2 || !props.habilitation.validated_n2_at) {
        return false;
    }
    return true;
});

// Étape 6 : Exécution IT - accessible uniquement aux admins
const canAccessEtape6 = computed(() => 
    isAdmin.value && (props.habilitation.status === 'approved' || props.habilitation.status === 'in_progress')
);

// Le bouton d'impression est disponible si le contrôle a validé
const canDownloadPdf = computed(() => 
    props.habilitation.validator_control && props.habilitation.validated_control_at
);

// Fonction pour télécharger le PDF
const downloadPdf = () => {
    window.open(`/habilitations/${props.habilitation.id}/pdf`, '_blank');
};

// Fonction pour confirmer et supprimer la demande
const confirmDelete = () => {
    const message = `Êtes-vous sûr de vouloir supprimer la demande d'habilitation #${props.habilitation.id} ?\n\n` +
                   `Demandeur: ${props.habilitation.requester.prenom} ${props.habilitation.requester.nom}\n` +
                   `Bénéficiaire: ${props.habilitation.beneficiary.prenom} ${props.habilitation.beneficiary.nom}\n\n` +
                   `Cette action est irréversible et supprimera définitivement la demande.`;
    
    if (confirm(message)) {
        router.delete(`/habilitations/${props.habilitation.id}`, {
            preserveScroll: false,
            onSuccess: () => {
                // La redirection est gérée par le contrôleur
            },
            onError: (errors) => {
                console.error('Erreur lors de la suppression:', errors);
                alert('Une erreur est survenue lors de la suppression de la demande.');
            }
        });
    }
};
</script>

<template>
    <Head :title="`Demande d'habilitation #${habilitation.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="w-full">
                    <h1 class="text-2xl font-bold bg-primary text-primary-foreground px-6 py-3 rounded-lg mb-3">DEMANDE D'HABILITATIONS #{{ habilitation.id }}</h1>
                    <p class="text-muted-foreground mt-1">
                        Statut : 
                        <span
                            :class="[
                                'rounded-full px-2 py-1 text-xs font-medium ml-2',
                                getStatutBadge(habilitation.status),
                            ]"
                        >
                            {{ getStatutLabel(habilitation.status) }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mb-4">
                <Link href="/habilitations">
                    <Button variant="outline">Retour à la liste</Button>
                </Link>
                <Button
                    v-if="canDownloadPdf"
                    @click="downloadPdf"
                    variant="default"
                >
                    <Printer class="mr-2 h-5 w-5" />
                    Télécharger le document
                </Button>
                <Button
                    v-if="isAdmin"
                    variant="destructive"
                    @click="confirmDelete"
                >
                    Supprimer la demande
                </Button>
            </div>

            <!-- Actions rapides selon le statut -->
            <div class="rounded-lg border border-sidebar-border bg-card p-4" v-if="canAccessEtape2 || canAccessEtape3 || canAccessEtape4 || canAccessEtape5 || canAccessEtape6">
                <h3 class="font-semibold mb-3">
                    Actions disponibles
                </h3>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="canAccessEtape2" :href="etape2.url({ habilitation: habilitation.id })">
                        <Button variant="outline">Étape 2 : Définir les droits</Button>
                    </Link>
                    <Link v-if="canAccessEtape3" :href="etape3.url({ habilitation: habilitation.id })">
                        <Button>Valider N+1</Button>
                    </Link>
                    <Link v-if="canAccessEtape4" :href="etape4.url({ habilitation: habilitation.id })">
                        <Button>Valider N+2</Button>
                    </Link>
                    <Link v-if="canAccessEtape5" :href="etape5.url({ habilitation: habilitation.id })">
                        <Button>Valider Contrôle</Button>
                    </Link>
                    <Link v-if="canAccessEtape6" :href="etape6.url({ habilitation: habilitation.id })">
                        <Button variant="outline">Exécuter IT</Button>
                    </Link>
                </div>
            </div>

            <!-- Informations générales -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 -mx-6 -mt-6 mb-4 rounded-t-lg">Informations générales</h2>
                
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-2">Demandeur</h3>
                            <p class="text-sm">{{ habilitation.requester.prenom }} {{ habilitation.requester.nom }}</p>
                            <p class="text-xs text-muted-foreground">Matricule: {{ habilitation.requester.matricule }}</p>
                        </div>
                        
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-2">Bénéficiaire</h3>
                            <p class="text-sm">{{ habilitation.beneficiary.prenom }} {{ habilitation.beneficiary.nom }}</p>
                            <p class="text-xs text-muted-foreground">Matricule: {{ habilitation.beneficiary.matricule }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-2">Type de demande</h3>
                            <p class="text-sm">{{ habilitation.request_type }}</p>
                        </div>
                        
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-2">Date de création</h3>
                            <p class="text-sm">{{ new Date(habilitation.created_at).toLocaleDateString('fr-FR', { 
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

            <!-- Détails de la demande -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6" v-if="habilitation.requested_profile || habilitation.applications">
                <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 -mx-6 -mt-6 mb-4 rounded-t-lg">Détails de la demande</h2>
                
                <div class="space-y-4">
                    <div v-if="habilitation.profile_type">
                        <h3 class="font-medium text-sm text-muted-foreground mb-2">Type de profil</h3>
                        <p class="text-sm">{{ habilitation.profile_type }}</p>
                    </div>

                    <div v-if="habilitation.requested_profile">
                        <h3 class="font-medium text-sm text-muted-foreground mb-2">Profil demandé</h3>
                        <p class="text-sm whitespace-pre-wrap">{{ habilitation.requested_profile }}</p>
                    </div>

                    <div v-if="habilitation.specific_profile">
                        <h3 class="font-medium text-sm text-muted-foreground mb-2">Profil spécifique</h3>
                        <p class="text-sm">{{ habilitation.specific_profile }}</p>
                    </div>

                    <div v-if="habilitation.applications && habilitation.applications.length > 0">
                        <h3 class="font-medium text-sm text-muted-foreground mb-2">Applications / Services</h3>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="app in habilitation.applications"
                                :key="app"
                                class="inline-flex items-center rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                            >
                                {{ app }}
                            </span>
                        </div>
                    </div>

                    <div v-if="habilitation.other_application">
                        <h3 class="font-medium text-sm text-muted-foreground mb-2">Autre application</h3>
                        <p class="text-sm">{{ habilitation.other_application }}</p>
                    </div>
                </div>
            </div>

            <!-- Historique des validations -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 -mx-6 -mt-6 mb-4 rounded-t-lg">Historique des validations</h2>
                
                <div class="space-y-4">
                    <!-- Validation N+1 -->
                    <div v-if="habilitation.validator_n1" class="border-l-4 border-blue-500 pl-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium">Validation N+1</h3>
                                <p class="text-sm text-muted-foreground">
                                    Validé par {{ habilitation.validator_n1.name }}
                                    <span v-if="habilitation.validated_n1_at">
                                        le {{ new Date(habilitation.validated_n1_at).toLocaleDateString('fr-FR') }}
                                    </span>
                                </p>
                            </div>
                            <span class="text-green-600 text-sm font-medium">✓ Validé</span>
                        </div>
                        <p v-if="habilitation.comment_n1" class="text-sm mt-2 text-muted-foreground">
                            {{ habilitation.comment_n1 }}
                        </p>
                    </div>

                    <!-- Validation N+2 -->
                    <div v-if="habilitation.validator_n2" class="border-l-4 border-orange-500 pl-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium">Validation N+2</h3>
                                <p class="text-sm text-muted-foreground">
                                    Validé par {{ habilitation.validator_n2.name }}
                                    <span v-if="habilitation.validated_n2_at">
                                        le {{ new Date(habilitation.validated_n2_at).toLocaleDateString('fr-FR') }}
                                    </span>
                                </p>
                            </div>
                            <span class="text-green-600 text-sm font-medium">✓ Validé</span>
                        </div>
                        <p v-if="habilitation.comment_n2" class="text-sm mt-2 text-muted-foreground">
                            {{ habilitation.comment_n2 }}
                        </p>
                    </div>

                    <!-- Validation Contrôle Permanent -->
                    <div v-if="habilitation.validator_control" class="border-l-4 pl-4"
                         :class="habilitation.status === 'rejected' ? 'border-red-500' : 'border-yellow-500'">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium">Validation Contrôle Permanent</h3>
                                <p class="text-sm text-muted-foreground">
                                    Validé par {{ habilitation.validator_control.name }}
                                    <span v-if="habilitation.validated_control_at">
                                        le {{ new Date(habilitation.validated_control_at).toLocaleDateString('fr-FR') }}
                                    </span>
                                </p>
                            </div>
                            <span 
                                class="text-sm font-medium"
                                :class="habilitation.status === 'rejected' ? 'text-red-600' : 'text-green-600'"
                            >
                                {{ habilitation.status === 'rejected' ? '✗ Rejeté' : '✓ Validé' }}
                            </span>
                        </div>
                        <p v-if="habilitation.comment_control" class="text-sm mt-2 text-muted-foreground">
                            {{ habilitation.comment_control }}
                        </p>
                    </div>

                    <!-- Exécution IT -->
                    <div v-if="habilitation.executor_it" class="border-l-4 border-purple-500 pl-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium">Exécution IT</h3>
                                <p class="text-sm text-muted-foreground">
                                    Exécuté par {{ habilitation.executor_it.name }}
                                    <span v-if="habilitation.executed_it_at">
                                        le {{ new Date(habilitation.executed_it_at).toLocaleDateString('fr-FR') }}
                                    </span>
                                </p>
                            </div>
                            <span class="text-green-600 text-sm font-medium">✓ Exécuté</span>
                        </div>
                        <p v-if="habilitation.comment_it" class="text-sm mt-2 text-muted-foreground">
                            {{ habilitation.comment_it }}
                        </p>
                    </div>

                    <!-- Aucune validation -->
                    <div v-if="!habilitation.validator_n1 && !habilitation.validator_control && !habilitation.validator_n2 && !habilitation.executor_it" class="text-sm text-muted-foreground">
                        Aucune validation effectuée pour le moment.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

