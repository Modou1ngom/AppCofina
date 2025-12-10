<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { validerEtape3 } from '@/routes/habilitations';
import { ref, computed } from 'vue';
import SignaturePad from '@/components/SignaturePad.vue';

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
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
        title: 'Étape 3 : Validation N+1',
        href: '#',
    },
];

const form = useForm({
    action: '' as 'approuver' | 'rejeter' | '',
    comment_n1: '',
    signature_n1: '',
});

const signatureRef = ref<InstanceType<typeof SignaturePad> | null>(null);

const submit = (action: 'approuver' | 'rejeter') => {
    // Si rejet, vérifier qu'un commentaire est fourni
    if (action === 'rejeter' && !form.comment_n1) {
        form.setError('comment_n1', 'Un commentaire est obligatoire pour rejeter la demande.');
        return;
    }

    // Sauvegarder la signature si elle existe
    if (signatureRef.value) {
        const signature = signatureRef.value.save();
        if (signature) {
            form.signature_n1 = signature;
        }
    }

    // Réinitialiser les erreurs précédentes
    form.clearErrors();
    
    // Mettre à jour l'action et soumettre
    form.action = action;
    
    form.post(validerEtape3.url({ habilitation: props.habilitation.id }), {
        preserveScroll: true,
        onSuccess: () => {
            // La redirection est gérée par le contrôleur
        },
        onError: (errors) => {
            Object.keys(errors).forEach((key) => {
                form.setError(key as any, Array.isArray(errors[key]) ? errors[key][0] : errors[key]);
            });
        }
    });
};
</script>

<template>
    <Head title="Étape 3 : Validation N+1" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold bg-primary text-primary-foreground px-6 py-3 rounded-lg mb-3">ÉTAPE 3 : VALIDATION N+1</h1>
                <p class="text-muted-foreground mt-1">Valider ou rejeter la demande d'habilitation en tant que N+1 du bénéficiaire</p>
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

            </div>

            <!-- Formulaire de validation -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <form @submit.prevent class="space-y-6">
                    <!-- Actions disponibles -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Actions disponibles</h2>
                        <p class="text-sm text-muted-foreground">Étape 3 : Validation N+1</p>
                        <InputError :message="form.errors.action" />
                    </div>

                    <!-- Commentaire -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Commentaire</h2>
                        
                        <div class="grid gap-2">
                            <Label for="comment_n1">
                                Commentaire 
                                <span class="text-muted-foreground text-sm font-normal">(obligatoire pour rejeter, optionnel pour valider)</span>
                            </Label>
                            <textarea
                                id="comment_n1"
                                v-model="form.comment_n1"
                                name="comment_n1"
                                rows="4"
                                class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Ajouter un commentaire si nécessaire (obligatoire pour rejeter)"
                            ></textarea>
                            <InputError :message="form.errors.comment_n1" />
                        </div>
                    </div>

                    <!-- Signature électronique -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Signature électronique</h2>
                        <p class="text-sm text-muted-foreground">Veuillez signer dans le champ ci-dessous</p>
                        <SignaturePad
                            ref="signatureRef"
                            v-model="form.signature_n1"
                            :width="500"
                            :height="200"
                        />
                        <InputError :message="form.errors.signature_n1" />
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
                        <div class="flex gap-3">
                            <Button
                                type="button"
                                variant="destructive"
                                @click="submit('rejeter')"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Traitement...</span>
                                <span v-else>Rejeter</span>
                            </Button>
                            <Button
                                type="button"
                                @click="submit('approuver')"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Traitement...</span>
                                <span v-else>Valider</span>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

