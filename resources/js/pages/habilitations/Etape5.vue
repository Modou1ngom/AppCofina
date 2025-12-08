<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { validerEtape5 } from '@/routes/habilitations';
import { ref, computed } from 'vue';

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
    validator_n1?: User;
    validator_n2?: User;
    validated_n1_at?: string;
    validated_n2_at?: string;
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
        title: 'Étape 5 : Validation Contrôle Permanent',
        href: '#',
    },
];

const form = useForm({
    action: '' as 'approuver' | 'rejeter' | '',
    comment_control: '',
});

const actionSelected = ref<'approuver' | 'rejeter' | ''>('');
const showCommentField = computed(() => actionSelected.value === 'rejeter');

const submit = () => {
    if (!actionSelected.value) {
        form.setError('action', 'Veuillez sélectionner une action.');
        return;
    }

    form.action = actionSelected.value;
    
    form.post(validerEtape5.url({ habilitation: props.habilitation.id }), {
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
    <Head title="Étape 5 : Validation Contrôle Permanent" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold bg-primary text-primary-foreground px-6 py-3 rounded-lg mb-3">ÉTAPE 5 : VALIDATION CONTRÔLE PERMANENT</h1>
                <p class="text-muted-foreground mt-1">Valider ou rejeter la demande d'habilitation en tant que Contrôle Permanent</p>
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

                <!-- Validation N+1 -->
                <div class="space-y-2 border-l-4 border-blue-500 pl-4 mt-4" v-if="habilitation.validator_n1">
                    <h4 class="font-medium text-sm text-muted-foreground">Validation N+1</h4>
                    <p class="text-sm">Validé par {{ habilitation.validator_n1.name }}</p>
                    <p class="text-xs text-muted-foreground" v-if="habilitation.validated_n1_at">
                        le {{ new Date(habilitation.validated_n1_at).toLocaleDateString('fr-FR') }}
                    </p>
                    <p class="text-sm whitespace-pre-wrap mt-2" v-if="habilitation.comment_n1">
                        <strong>Commentaire :</strong> {{ habilitation.comment_n1 }}
                    </p>
                </div>

                <!-- Validation N+2 -->
                <div class="space-y-2 border-l-4 border-orange-500 pl-4 mt-4" v-if="habilitation.validator_n2">
                    <h4 class="font-medium text-sm text-muted-foreground">Validation N+2</h4>
                    <p class="text-sm">Validé par {{ habilitation.validator_n2.name }}</p>
                    <p class="text-xs text-muted-foreground" v-if="habilitation.validated_n2_at">
                        le {{ new Date(habilitation.validated_n2_at).toLocaleDateString('fr-FR') }}
                    </p>
                    <p class="text-sm whitespace-pre-wrap mt-2" v-if="habilitation.comment_n2">
                        <strong>Commentaire :</strong> {{ habilitation.comment_n2 }}
                    </p>
                </div>
            </div>

            <!-- Formulaire de validation -->
            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Action -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Décision *</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div
                                class="flex items-center space-x-2 p-4 border rounded-lg cursor-pointer transition-colors"
                                :class="actionSelected === 'approuver' ? 'border-primary bg-primary/5' : 'border-input'"
                                @click="actionSelected = 'approuver'"
                            >
                                <input
                                    type="radio"
                                    id="action_approuver"
                                    name="action"
                                    value="approuver"
                                    v-model="actionSelected"
                                    class="h-5 w-5 text-primary"
                                />
                                <Label for="action_approuver" class="cursor-pointer flex-1">
                                    <div class="font-medium">Approuver</div>
                                    <div class="text-sm text-muted-foreground">Valider la demande et passer à l'exécution IT</div>
                                </Label>
                            </div>

                            <div
                                class="flex items-center space-x-2 p-4 border rounded-lg cursor-pointer transition-colors"
                                :class="actionSelected === 'rejeter' ? 'border-destructive bg-destructive/5' : 'border-input'"
                                @click="actionSelected = 'rejeter'"
                            >
                                <input
                                    type="radio"
                                    id="action_rejeter"
                                    name="action"
                                    value="rejeter"
                                    v-model="actionSelected"
                                    class="h-5 w-5 text-destructive"
                                />
                                <Label for="action_rejeter" class="cursor-pointer flex-1">
                                    <div class="font-medium">Rejeter</div>
                                    <div class="text-sm text-muted-foreground">Rejeter la demande (commentaire obligatoire)</div>
                                </Label>
                            </div>
                        </div>
                        <InputError :message="form.errors.action" />
                    </div>

                    <!-- Commentaire -->
                    <div class="space-y-4" v-if="showCommentField">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Commentaire *</h2>
                        
                        <div class="grid gap-2">
                            <Label for="comment_control">Motif du rejet</Label>
                            <textarea
                                id="comment_control"
                                v-model="form.comment_control"
                                name="comment_control"
                                rows="4"
                                required
                                class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Expliquer le motif du rejet de la demande"
                            ></textarea>
                            <InputError :message="form.errors.comment_control" />
                        </div>
                    </div>

                    <!-- Commentaire optionnel pour approbation -->
                    <div class="space-y-4" v-if="actionSelected === 'approuver'">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Commentaire (optionnel)</h2>
                        
                        <div class="grid gap-2">
                            <Label for="comment_control_optional">Commentaire</Label>
                            <textarea
                                id="comment_control_optional"
                                v-model="form.comment_control"
                                name="comment_control"
                                rows="4"
                                class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Ajouter un commentaire si nécessaire"
                            ></textarea>
                            <InputError :message="form.errors.comment_control" />
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
                            :disabled="form.processing || !actionSelected"
                            :variant="actionSelected === 'rejeter' ? 'destructive' : 'default'"
                        >
                            <span v-if="form.processing">Traitement...</span>
                            <span v-else-if="actionSelected === 'approuver'">Approuver la demande</span>
                            <span v-else-if="actionSelected === 'rejeter'">Rejeter la demande</span>
                            <span v-else>Valider</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

