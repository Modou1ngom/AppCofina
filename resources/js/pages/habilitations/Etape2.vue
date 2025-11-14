<script setup lang="ts">
import { Head, Form, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { updateEtape2 } from '@/routes/habilitations';
import { ref, computed } from 'vue';

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
    applications: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
        href: '/habilitations',
    },
    {
        title: 'Étape 2 : Définition des droits et habilitations',
        href: '#',
    },
];

const form = useForm({
    requested_profile: props.habilitation.requested_profile || '',
    profile_type: (props.habilitation.profile_type as 'Consultation simple' | 'Profil Specifique' | '') || '',
    specific_profile: props.habilitation.specific_profile || '',
    applications: props.habilitation.applications || [] as string[],
    other_application: props.habilitation.other_application || '',
    comment_n1: props.habilitation.comment_n1 || '',
});

const selectedApplications = ref<string[]>(props.habilitation.applications || []);
const showAutreApplication = computed(() => selectedApplications.value.includes('Autres'));
const showProfilSpecifique = computed(() => form.profile_type === 'Profil Specifique');

const toggleApplication = (app: string) => {
    const index = selectedApplications.value.indexOf(app);
    if (index > -1) {
        selectedApplications.value.splice(index, 1);
    } else {
        selectedApplications.value.push(app);
    }
    form.applications = selectedApplications.value;
};

const submit = () => {
    // S'assurer que les applications sont bien dans le formulaire
    form.applications = selectedApplications.value;
    
    form.put(updateEtape2.url({ habilitation: props.habilitation.id }));
};
</script>

<template>
    <Head title="Étape 2 : Définition des droits et habilitations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold">Étape 2 : Définition des droits et habilitations</h1>
                <p class="text-muted-foreground mt-1">Définir les droits et habilitations par N+1</p>
            </div>

            <!-- Informations de la demande -->
            <div class="rounded-lg border border-sidebar-border bg-card p-4">
                <h3 class="font-semibold mb-3">Informations de la demande</h3>
                <div class="grid gap-2 text-sm">
                    <div><span class="font-medium">Demandeur :</span> {{ habilitation.requester.prenom }} {{ habilitation.requester.nom }}</div>
                    <div><span class="font-medium">Bénéficiaire :</span> {{ habilitation.beneficiary.prenom }} {{ habilitation.beneficiary.nom }}</div>
                    <div><span class="font-medium">Type de demande :</span> {{ habilitation.request_type }}</div>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <Form
                    v-bind="updateEtape2.form({ habilitation: habilitation.id })"
                    @submit.prevent="submit"
                    class="space-y-8"
                    v-slot="{ processing }"
                >
                    <!-- Profil demandé -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Profil demandé *</h2>
                        
                        <div class="grid gap-2">
                            <Label for="requested_profile">Description du profil demandé</Label>
                            <textarea
                                id="requested_profile"
                                v-model="form.requested_profile"
                                name="requested_profile"
                                rows="4"
                                required
                                class="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Décrire en détail le profil demandé pour le bénéficiaire"
                            ></textarea>
                            <InputError :message="form.errors.requested_profile" />
                        </div>
                    </div>

                    <!-- Type de profil -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Type de profil *</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="profile_type">Type de profil</Label>
                                <select
                                    id="profile_type"
                                    v-model="form.profile_type"
                                    name="profile_type"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Sélectionner un type</option>
                                    <option value="Consultation simple">Consultation simple</option>
                                    <option value="Profil Specifique">Profil Spécifique</option>
                                </select>
                                <InputError :message="form.errors.profile_type" />
                            </div>

                            <div v-if="showProfilSpecifique" class="grid gap-2">
                                <Label for="specific_profile">Profil spécifique *</Label>
                                <Input
                                    id="specific_profile"
                                    v-model="form.specific_profile"
                                    name="specific_profile"
                                    placeholder="Décrire le profil spécifique"
                                    :required="showProfilSpecifique"
                                />
                                <InputError :message="form.errors.specific_profile" />
                            </div>
                        </div>
                    </div>

                    <!-- Applications -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Applications / Services demandés *</h2>
                        
                        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="app in applications"
                                :key="app"
                                class="flex items-center space-x-2"
                            >
                                <Checkbox
                                    :id="`app-${app}`"
                                    :checked="selectedApplications.includes(app)"
                                    @update:checked="toggleApplication(app)"
                                />
                                <Label
                                    :for="`app-${app}`"
                                    class="text-sm font-normal cursor-pointer"
                                >
                                    {{ app }}
                                </Label>
                            </div>
                        </div>
                        <InputError :message="form.errors.applications" />

                        <div v-if="showAutreApplication" class="grid gap-2">
                            <Label for="other_application">Préciser l'application</Label>
                            <Input
                                id="other_application"
                                v-model="form.other_application"
                                name="other_application"
                                placeholder="Nom de l'application"
                            />
                            <InputError :message="form.errors.other_application" />
                        </div>
                    </div>

                    <!-- Commentaire N+1 -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Commentaire N+1</h2>
                        
                        <div class="grid gap-2">
                            <Label for="comment_n1">Commentaire (optionnel)</Label>
                            <textarea
                                id="comment_n1"
                                v-model="form.comment_n1"
                                name="comment_n1"
                                rows="4"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Ajouter un commentaire si nécessaire"
                            ></textarea>
                            <InputError :message="form.errors.comment_n1" />
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
                            :disabled="processing || form.processing"
                        >
                            <span v-if="processing || form.processing">Enregistrement...</span>
                            <span v-else>Valider et passer au contrôle permanent</span>
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>

