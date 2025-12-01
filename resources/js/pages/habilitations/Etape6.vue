<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { executerEtape6 } from '@/routes/habilitations';

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
    beneficiary: Profil;
    request_type: string;
    requested_profile?: string;
    profile_type?: string;
    specific_profile?: string;
    applications?: string[];
    other_application?: string;
    comment_n1?: string;
    comment_n2?: string;
    comment_control?: string;
    validator_n1?: User;
    validator_n2?: User;
    validator_control?: User;
    validated_n1_at?: string;
    validated_n2_at?: string;
    validated_control_at?: string;
    status: string;
}

interface Props {
    habilitation: Habilitation;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
        href: '/habilitations',
    },
    {
        title: 'Étape 6 : Exécution IT',
        href: '#',
    },
];

const form = useForm({
    comment_it: '',
    notify_requester: false,
    notify_n1: false,
});

const submit = () => {
    form.post(executerEtape6.url({ habilitation: props.habilitation.id }), {
        preserveScroll: true,
        onError: (errors) => {
            Object.keys(errors).forEach((key) => {
                form.setError(key as any, Array.isArray(errors[key]) ? errors[key][0] : errors[key]);
            });
        }
    });
};
</script>

<template>
    <Head title="Étape 6 : Exécution IT" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold bg-primary text-primary-foreground px-6 py-3 rounded-lg mb-3">ÉTAPE 6 : EXÉCUTION IT</h1>
                <p class="text-muted-foreground mt-1">Exécuter la demande d'habilitation et envoyer les notifications</p>
            </div>

            <!-- Informations de la demande -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6 space-y-4">
                <h3 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 -mx-6 -mt-6 mb-4 rounded-t-lg">Résumé de la demande</h3>
                
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <h4 class="font-medium text-sm text-muted-foreground">Demandeur</h4>
                        <p class="text-sm">{{ habilitation.requester.prenom }} {{ habilitation.requester.nom }}</p>
                    </div>
                    
                    <div class="space-y-2">
                        <h4 class="font-medium text-sm text-muted-foreground">Bénéficiaire</h4>
                        <p class="text-sm">{{ habilitation.beneficiary.prenom }} {{ habilitation.beneficiary.nom }}</p>
                    </div>
                    
                    <div class="space-y-2">
                        <h4 class="font-medium text-sm text-muted-foreground">Type de demande</h4>
                        <p class="text-sm">{{ habilitation.request_type }}</p>
                    </div>
                    
                    <div class="space-y-2" v-if="habilitation.profile_type">
                        <h4 class="font-medium text-sm text-muted-foreground">Type de profil</h4>
                        <p class="text-sm">{{ habilitation.profile_type }}</p>
                    </div>
                </div>

                <div class="space-y-2" v-if="habilitation.requested_profile">
                    <h4 class="font-medium text-sm text-muted-foreground">Profil demandé</h4>
                    <p class="text-sm whitespace-pre-wrap">{{ habilitation.requested_profile }}</p>
                </div>

                <div class="space-y-2" v-if="habilitation.applications && habilitation.applications.length > 0">
                    <h4 class="font-medium text-sm text-muted-foreground">Applications / Services</h4>
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

                <!-- Historique des validations -->
                <div class="space-y-3 mt-4">
                    <h4 class="font-medium text-sm text-muted-foreground">Historique des validations</h4>
                    
                    <div class="space-y-2 border-l-4 border-blue-500 pl-4" v-if="habilitation.validator_n1">
                        <p class="text-sm"><strong>N+1 :</strong> Validé par {{ habilitation.validator_n1.name }}</p>
                        <p class="text-xs text-muted-foreground" v-if="habilitation.validated_n1_at">
                            le {{ new Date(habilitation.validated_n1_at).toLocaleDateString('fr-FR') }}
                        </p>
                    </div>

                    <div class="space-y-2 border-l-4 border-orange-500 pl-4" v-if="habilitation.validator_n2">
                        <p class="text-sm"><strong>N+2 :</strong> Validé par {{ habilitation.validator_n2.name }}</p>
                        <p class="text-xs text-muted-foreground" v-if="habilitation.validated_n2_at">
                            le {{ new Date(habilitation.validated_n2_at).toLocaleDateString('fr-FR') }}
                        </p>
                    </div>

                    <div class="space-y-2 border-l-4 border-yellow-500 pl-4" v-if="habilitation.validator_control">
                        <p class="text-sm"><strong>Contrôle :</strong> Validé par {{ habilitation.validator_control.name }}</p>
                        <p class="text-xs text-muted-foreground" v-if="habilitation.validated_control_at">
                            le {{ new Date(habilitation.validated_control_at).toLocaleDateString('fr-FR') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Formulaire d'exécution -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Commentaire -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Commentaire (optionnel)</h2>
                        
                        <div class="grid gap-2">
                            <Label for="comment_it">Commentaire sur l'exécution</Label>
                            <textarea
                                id="comment_it"
                                v-model="form.comment_it"
                                name="comment_it"
                                rows="4"
                                class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Ajouter un commentaire sur l'exécution si nécessaire"
                            ></textarea>
                            <InputError :message="form.errors.comment_it" />
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Notifications</h2>
                        
                        <div class="space-y-3">
                            <div class="flex items-center space-x-2">
                                <Checkbox
                                    id="notify_requester"
                                    v-model:checked="form.notify_requester"
                                />
                                <Label for="notify_requester" class="cursor-pointer">
                                    Notifier le demandeur ({{ habilitation.requester.prenom }} {{ habilitation.requester.nom }})
                                </Label>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <Checkbox
                                    id="notify_n1"
                                    v-model:checked="form.notify_n1"
                                />
                                <Label for="notify_n1" class="cursor-pointer">
                                    Notifier le N+1
                                    <span v-if="habilitation.validator_n1">
                                        ({{ habilitation.validator_n1.name }})
                                    </span>
                                </Label>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between gap-4 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            @click="router.visit(`/habilitations/${habilitation.id}`)"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Exécution en cours...</span>
                            <span v-else>Finaliser l'exécution</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

