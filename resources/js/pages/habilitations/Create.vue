<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/habilitations';
import { computed, watch } from 'vue';

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

interface Filiale {
    id: number;
    nom: string;
}

interface Props {
    profils?: Profil[];
    applications: string[];
    demandeur?: Profil;
    beneficiaire?: Profil;
    filiales?: Filiale[];
}

const props = defineProps<Props>();

// Debug: vérifier que les applications sont bien reçues
console.log('Applications reçues:', props.applications);
console.log('Nombre d\'applications:', props.applications?.length);
console.log('Demandeur:', props.demandeur);
console.log('Bénéficiaire:', props.beneficiaire);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
        href: '/habilitations',
    },
    {
        title: 'Étape 1 : Informations de base',
        href: '#',
    },
];

const form = useForm({
    // Demandeur
    requester_profile_id: (props.demandeur?.id || '') as string | number,
    requester_direction: props.demandeur?.departement || '',
    requester_email: props.demandeur?.email || '',
    requester_telephone: props.demandeur?.telephone || '',
    
    // Bénéficiaire
    beneficiary_profile_id: (props.beneficiaire?.id || '') as string | number,
    beneficiary_direction: props.beneficiaire?.departement || '',
    beneficiary_email: props.beneficiaire?.email || '',
    beneficiary_telephone: props.beneficiaire?.telephone || '',
    beneficiary_site: props.beneficiaire?.site || '',
    
    // Détails de la demande
    request_type: 'Creation' as 'Creation' | 'Modification' | 'Desactivation' | 'Suppression',
    applications: [] as string[],
    other_application: '',
    current_profile: '',
    requested_profile: '',
    desired_implementation_date: '',
    profile_type: '' as 'Consultation simple' | 'Profil Specifique' | '',
    specific_profile: '',
    validity_period: 'Permanent' as 'Permanent' | 'Temporaire',
    start_date: '',
    end_date: '',
    request_reason: '',
    subsidiary: 'SENEGAL',
});

const showAutreApplication = computed(() => form.applications.includes('Autres'));
const showProfilSpecifique = computed(() => form.profile_type === 'Profil Specifique');
const showDatesTemporaire = computed(() => form.validity_period === 'Temporaire');
const showProfilActuel = computed(() => form.request_type === 'Modification');

// Watcher pour remplir automatiquement les informations du demandeur
watch(() => form.requester_profile_id, (newProfileId) => {
    // Ne pas remplir si le demandeur est déjà pré-rempli
    if (props.demandeur) {
        return;
    }
    if (newProfileId && props.profils) {
        const selectedProfil = props.profils.find(p => p.id === Number(newProfileId));
        if (selectedProfil) {
            form.requester_direction = selectedProfil.departement || '';
            form.requester_email = selectedProfil.email || '';
            form.requester_telephone = selectedProfil.telephone || '';
        }
    } else {
        // Réinitialiser les champs si aucun profil n'est sélectionné
        form.requester_direction = '';
        form.requester_email = '';
        form.requester_telephone = '';
    }
});

// Watcher pour remplir automatiquement les informations du bénéficiaire
watch(() => form.beneficiary_profile_id, (newProfileId) => {
    // Ne pas remplir si le bénéficiaire est déjà pré-rempli
    if (props.beneficiaire) {
        return;
    }
    if (newProfileId && props.profils) {
        const selectedProfil = props.profils.find(p => p.id === Number(newProfileId));
        if (selectedProfil) {
            form.beneficiary_direction = selectedProfil.departement || '';
            form.beneficiary_email = selectedProfil.email || '';
            form.beneficiary_telephone = selectedProfil.telephone || '';
            form.beneficiary_site = selectedProfil.site || '';
        }
    } else {
        // Réinitialiser les champs si aucun profil n'est sélectionné
        form.beneficiary_direction = '';
        form.beneficiary_email = '';
        form.beneficiary_telephone = '';
        form.beneficiary_site = '';
    }
});

const toggleApplication = (app: string, checked: boolean) => {
    console.log('toggleApplication appelé:', { app, checked, current: form.applications });
    
    // Créer un nouveau tableau pour forcer la réactivité d'Inertia
    if (checked) {
        if (!form.applications.includes(app)) {
            form.applications = [...form.applications, app];
        }
    } else {
        form.applications = form.applications.filter(a => a !== app);
    }
    
    console.log('Après toggle - form.applications:', form.applications);
};

const submit = () => {
    console.log('Submit habilitation - form.applications:', form.applications);
    console.log('Submit habilitation - form.data():', form.data());
    
    // Vérifier que au moins une application est sélectionnée
    if (!form.applications || form.applications.length === 0) {
        form.setError('applications', 'Veuillez sélectionner au moins une application.');
        return;
    }
    
    // Vérifier que form.applications est bien un tableau
    if (!Array.isArray(form.applications)) {
        console.error('ERROR: form.applications n\'est pas un tableau!', form.applications);
        form.applications = [];
        form.setError('applications', 'Erreur: les applications ne sont pas valides.');
        return;
    }
    
    // S'assurer que les données sont correctement formatées avant l'envoi
    const applicationsData = Array.isArray(form.applications) ? form.applications : [];
    
    // Utiliser transform pour s'assurer que les applications sont bien incluses
    form.transform((data) => {
        return {
            ...data,
            applications: applicationsData,
            applications_json: JSON.stringify(applicationsData),
        };
    }).post(store.url(), {
        preserveScroll: true,
        preserveState: false,
        onError: (errors) => {
            console.log('Erreurs reçues:', errors);
            if (errors.message && errors.message.includes('403')) {
                console.error('Erreur 403: Problème de permissions');
            }
            Object.keys(errors).forEach((key) => {
                form.setError(key as any, Array.isArray(errors[key]) ? errors[key][0] : errors[key]);
            });
        },
        onSuccess: () => {
            console.log('Habilitation créée avec succès');
        }
    });
};

</script>

<template>
    <Head title="Étape 1 : Informations de base - Nouvelle demande d'habilitation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold bg-primary text-primary-foreground px-6 py-3 rounded-lg mb-3">ÉTAPE 1 : INFORMATIONS DE BASE</h1>
                <p class="text-muted-foreground mt-1">Renseigner les détails de la demande</p>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <form
                    @submit.prevent="submit"
                    class="space-y-8"
                >
                    <!-- Section Demandeur -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Informations du demandeur</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="requester_profile_id">Profil du demandeur *</Label>
                                <div v-if="props.demandeur" class="flex h-9 items-center rounded-md border border-input px-3 text-sm">
                                    {{ props.demandeur.prenom }} {{ props.demandeur.nom }} 
                                </div>
                                <select
                                    v-else
                                    id="requester_profile_id"
                                    v-model="form.requester_profile_id"
                                    name="requester_profile_id"
                                    required
                                    class="block w-full h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled selected>Sélectionner un profil</option>
                                    <option
                                        v-for="profil in props.profils || []"
                                        :key="profil.id"
                                        :value="profil.id"
                                    >
                                        {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                    </option>
                                </select>
                                <p v-if="!props.demandeur && (!props.profils || props.profils.length === 0)" class="text-sm text-muted-foreground">Aucun profil disponible. Veuillez d'abord créer des profils.</p>
                                <InputError :message="form.errors.requester_profile_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="requester_direction">Direction</Label>
                                <Input
                                    id="requester_direction"
                                    v-model="form.requester_direction"
                                    name="requester_direction"
                                    placeholder="Direction du demandeur"
                                    :disabled="!!props.demandeur"
                                />
                                <InputError :message="form.errors.requester_direction" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="requester_email">Email</Label>
                                <Input
                                    id="requester_email"
                                    v-model="form.requester_email"
                                    type="email"
                                    name="requester_email"
                                    placeholder="email@example.com"
                                    :disabled="!!props.demandeur"
                                />
                                <InputError :message="form.errors.requester_email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="requester_telephone">Téléphone</Label>
                                <Input
                                    id="requester_telephone"
                                    v-model="form.requester_telephone"
                                    name="requester_telephone"
                                    placeholder="+221 XX XXX XX XX"
                                    :disabled="!!props.demandeur"
                                />
                                <InputError :message="form.errors.requester_telephone" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Bénéficiaire -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Informations du bénéficiaire</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="beneficiary_profile_id">Profil du bénéficiaire *</Label>
                                <div v-if="props.beneficiaire" class="flex h-9 items-center rounded-md border border-input  px-3 text-sm">
                                    {{ props.beneficiaire.prenom }} {{ props.beneficiaire.nom }} 
                                </div>
                                <select
                                    v-else
                                    id="beneficiary_profile_id"
                                    v-model="form.beneficiary_profile_id"
                                    name="beneficiary_profile_id"
                                    required
                                    class="block w-full h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled selected>Sélectionner un profil</option>
                                    <option
                                        v-for="profil in props.profils || []"
                                        :key="profil.id"
                                        :value="profil.id"
                                    >
                                        {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                    </option>
                                </select>
                                <p v-if="!props.beneficiaire && (!props.profils || props.profils.length === 0)" class="text-sm text-muted-foreground">Aucun profil disponible. Veuillez d'abord créer des profils.</p>
                                <InputError :message="form.errors.beneficiary_profile_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiary_direction">Direction</Label>
                                <Input
                                    id="beneficiary_direction"
                                    v-model="form.beneficiary_direction"
                                    name="beneficiary_direction"
                                    placeholder="Direction du bénéficiaire"
                                    :disabled="!!props.beneficiaire"
                                />
                                <InputError :message="form.errors.beneficiary_direction" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiary_email">Email</Label>
                                <Input
                                    id="beneficiary_email"
                                    v-model="form.beneficiary_email"
                                    type="email"
                                    name="beneficiary_email"
                                    placeholder="email@example.com"
                                    :disabled="!!props.beneficiaire"
                                />
                                <InputError :message="form.errors.beneficiary_email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiary_telephone">Téléphone</Label>
                                <Input
                                    id="beneficiary_telephone"
                                    v-model="form.beneficiary_telephone"
                                    name="beneficiary_telephone"
                                    placeholder="+221 XX XXX XX XX"
                                    :disabled="!!props.beneficiaire"
                                />
                                <InputError :message="form.errors.beneficiary_telephone" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiary_site">Agence</Label>
                                <Input
                                    id="beneficiary_site"
                                    v-model="form.beneficiary_site"
                                    name="beneficiary_site"
                                    placeholder="Site du bénéficiaire"
                                    :disabled="!!props.beneficiaire"
                                />
                                <InputError :message="form.errors.beneficiary_site" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Détails de la demande -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Détails de la demande</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="request_type">Type de demande *</Label>
                                <select
                                    id="request_type"
                                    v-model="form.request_type"
                                    name="request_type"
                                    required
                                    class="block w-full h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="Creation">Création</option>
                                    <option value="Modification">Modification</option>
                                    <option value="Desactivation">Désactivation</option>
                                    <option value="Suppression">Suppression</option>
                                    <option value="Suppression">Transfert</option>
                                </select>
                                <InputError :message="form.errors.request_type" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="subsidiary">Filiale</Label>
                                <select
                                    id="subsidiary"
                                    v-model="form.subsidiary"
                                    name="subsidiary"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="SENEGAL">SENEGAL</option>
                                    <option
                                        v-for="filiale in props.filiales || []"
                                        :key="filiale.id"
                                        :value="filiale.nom"
                                    >
                                        {{ filiale.nom }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.subsidiary" />
                            </div>

                            <div v-if="showProfilActuel" class="grid gap-2 md:col-span-2">
                                <Label for="current_profile">Profil actuel</Label>
                                <textarea
                                    id="current_profile"
                                    v-model="form.current_profile"
                                    name="current_profile"
                                    rows="3"
                                    class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Décrire le profil actuel du bénéficiaire"
                                ></textarea>
                                <InputError :message="form.errors.current_profile" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="requested_profile">Profil demandé</Label>
                                <textarea
                                    id="requested_profile"
                                    v-model="form.requested_profile"
                                    name="requested_profile"
                                    rows="3"
                                    class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Décrire le profil demandé"
                                ></textarea>
                                <InputError :message="form.errors.requested_profile" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="desired_implementation_date">Date d'implémentation souhaitée</Label>
                                <Input
                                    id="desired_implementation_date"
                                    v-model="form.desired_implementation_date"
                                    type="date"
                                    name="desired_implementation_date"
                                />
                                <InputError :message="form.errors.desired_implementation_date" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Applications -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Applications / Services demandés *</h2>
                        
                        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="app in applications"
                                :key="app"
                                class="flex items-center space-x-2 cursor-pointer"
                                @click="toggleApplication(app, !form.applications.includes(app))"
                            >
                                <Checkbox
                                    :id="`app-${app}`"
                                    :checked="form.applications.includes(app)"
                                    @update:checked="(checked: boolean) => {
                                        console.log('Checkbox update:checked déclenché pour app', app, checked);
                                        toggleApplication(app, checked);
                                    }"
                                    @click.stop
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

                    <!-- Section Type de profil -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Type de profil</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="profile_type">Type de profil</Label>
                                <select
                                    id="profile_type"
                                    v-model="form.profile_type"
                                    name="profile_type"
                                    class="block w-full h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
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

                    <!-- Section Période de validité -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Période de validité</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="validity_period">Période de validité</Label>
                                <select
                                    id="validity_period"
                                    v-model="form.validity_period"
                                    name="validity_period"
                                    class="block w-full h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="Permanent">Permanent</option>
                                    <option value="Temporaire">Temporaire</option>
                                </select>
                                <InputError :message="form.errors.validity_period" />
                            </div>

                            <div v-if="showDatesTemporaire" class="grid gap-2">
                                <Label for="start_date">Date de début *</Label>
                                <Input
                                    id="start_date"
                                    v-model="form.start_date"
                                    type="date"
                                    name="start_date"
                                    :required="showDatesTemporaire"
                                />
                                <InputError :message="form.errors.start_date" />
                            </div>

                            <div v-if="showDatesTemporaire" class="grid gap-2">
                                <Label for="end_date">Date de fin *</Label>
                                <Input
                                    id="end_date"
                                    v-model="form.end_date"
                                    type="date"
                                    name="end_date"
                                    :required="showDatesTemporaire"
                                />
                                <InputError :message="form.errors.end_date" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Motif -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold bg-primary text-primary-foreground px-4 py-2 rounded-md">Motif de la demande</h2>
                        
                        <div class="grid gap-2">
                            <Label for="request_reason">Motif</Label>
                            <textarea
                                id="request_reason"
                                v-model="form.request_reason"
                                name="request_reason"
                                rows="4"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Expliquer le motif de la demande d'habilitation"
                            ></textarea>
                            <InputError :message="form.errors.request_reason" />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            @click="router.visit('/habilitations')"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Enregistrement...</span>
                            <span v-else>Continuer vers l'étape 2</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

