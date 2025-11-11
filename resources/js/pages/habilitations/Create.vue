<script setup lang="ts">
import { Head, Form, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/habilitations';
import { ref, computed } from 'vue';

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
    fonction?: string;
    departement?: string;
}

interface Props {
    profils: Profil[];
    applications: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
        href: '/habilitations',
    },
    {
        title: 'Nouvelle demande - Étape 1',
        href: '#',
    },
];

const form = useForm({
    // Demandeur
    demandeur_profile_id: '' as number | string,
    demandeur_direction: '',
    demandeur_email: '',
    demandeur_telephone: '',
    
    // Bénéficiaire
    beneficiaire_profile_id: '' as number | string,
    beneficiaire_direction: '',
    beneficiaire_email: '',
    beneficiaire_telephone: '',
    beneficiaire_site: '',
    
    // Détails de la demande
    type_demande: 'Creation' as 'Creation' | 'Modification' | 'Desactivation' | 'Suppression',
    applications: [] as string[],
    autre_application: '',
    profil_actuel: '',
    profil_demande: '',
    date_implementation_souhaitee: '',
    type_profil: '' as 'Consultation simple' | 'Profil Specifique' | '',
    profil_specifique: '',
    periode_validite: 'Permanent' as 'Permanent' | 'Temporaire',
    date_debut: '',
    date_fin: '',
    motif_demande: '',
    filiale: '',
});

const selectedApplications = ref<string[]>([]);
const showAutreApplication = computed(() => selectedApplications.value.includes('Autres'));
const showProfilSpecifique = computed(() => form.type_profil === 'Profil Specifique');
const showDatesTemporaire = computed(() => form.periode_validite === 'Temporaire');
const showProfilActuel = computed(() => form.type_demande === 'Modification');

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
    
    if (form.applications.length === 0) {
        form.setError('applications', 'Veuillez sélectionner au moins une application.');
        return;
    }
    
    form.post(store.url());
};

const demandeurProfil = computed(() => {
    return props.profils.find(p => p.id === Number(form.demandeur_profile_id));
});

const beneficiaireProfil = computed(() => {
    return props.profils.find(p => p.id === Number(form.beneficiaire_profile_id));
});
</script>

<template>
    <Head title="Nouvelle demande d'habilitation - Étape 1" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold">Nouvelle demande d'habilitation</h1>
                <p class="text-muted-foreground mt-1">Étape 1 : Profil RH - Informations de base</p>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <Form
                    :action="store.url()"
                    method="post"
                    @submit.prevent="submit"
                    class="space-y-8"
                    v-slot="{ processing }"
                >
                    <!-- Section Demandeur -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Informations du demandeur</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="demandeur_profile_id">Profil du demandeur *</Label>
                                <select
                                    id="demandeur_profile_id"
                                    v-model.number="form.demandeur_profile_id"
                                    name="demandeur_profile_id"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Sélectionner un profil</option>
                                    <option
                                        v-for="profil in profils"
                                        :key="profil.id"
                                        :value="profil.id"
                                    >
                                        {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                    </option>
                                </select>
                                <InputError :message="form.errors.demandeur_profile_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="demandeur_direction">Direction</Label>
                                <Input
                                    id="demandeur_direction"
                                    v-model.number="form.demandeur_direction"
                                    name="demandeur_direction"
                                    placeholder="Direction du demandeur"
                                />
                                <InputError :message="form.errors.demandeur_direction" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="demandeur_email">Email</Label>
                                <Input
                                    id="demandeur_email"
                                    v-model.number="form.demandeur_email"
                                    type="email"
                                    name="demandeur_email"
                                    placeholder="email@example.com"
                                />
                                <InputError :message="form.errors.demandeur_email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="demandeur_telephone">Téléphone</Label>
                                <Input
                                    id="demandeur_telephone"
                                    v-model.number="form.demandeur_telephone"
                                    name="demandeur_telephone"
                                    placeholder="+221 XX XXX XX XX"
                                />
                                <InputError :message="form.errors.demandeur_telephone" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Bénéficiaire -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Informations du bénéficiaire</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="beneficiaire_profile_id">Profil du bénéficiaire *</Label>
                                <select
                                    id="beneficiaire_profile_id"
                                    v-model.number="form.beneficiaire_profile_id"
                                    name="beneficiaire_profile_id"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Sélectionner un profil</option>
                                    <option
                                        v-for="profil in profils"
                                        :key="profil.id"
                                        :value="profil.id"
                                    >
                                        {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                    </option>
                                </select>
                                <InputError :message="form.errors.beneficiaire_profile_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiaire_direction">Direction</Label>
                                <Input
                                    id="beneficiaire_direction"
                                    v-model.number="form.beneficiaire_direction"
                                    name="beneficiaire_direction"
                                    placeholder="Direction du bénéficiaire"
                                />
                                <InputError :message="form.errors.beneficiaire_direction" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiaire_email">Email</Label>
                                <Input
                                    id="beneficiaire_email"
                                    v-model.number="form.beneficiaire_email"
                                    type="email"
                                    name="beneficiaire_email"
                                    placeholder="email@example.com"
                                />
                                <InputError :message="form.errors.beneficiaire_email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiaire_telephone">Téléphone</Label>
                                <Input
                                    id="beneficiaire_telephone"
                                    v-model.number="form.beneficiaire_telephone"
                                    name="beneficiaire_telephone"
                                    placeholder="+221 XX XXX XX XX"
                                />
                                <InputError :message="form.errors.beneficiaire_telephone" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="beneficiaire_site">Site</Label>
                                <Input
                                    id="beneficiaire_site"
                                    v-model.number="form.beneficiaire_site"
                                    name="beneficiaire_site"
                                    placeholder="Site du bénéficiaire"
                                />
                                <InputError :message="form.errors.beneficiaire_site" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Détails de la demande -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Détails de la demande</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="type_demande">Type de demande *</Label>
                                <select
                                    id="type_demande"
                                    v-model.number="form.type_demande"
                                    name="type_demande"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="Creation">Création</option>
                                    <option value="Modification">Modification</option>
                                    <option value="Desactivation">Désactivation</option>
                                    <option value="Suppression">Suppression</option>
                                </select>
                                <InputError :message="form.errors.type_demande" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="filiale">Filiale</Label>
                                <Input
                                    id="filiale"
                                    v-model.number="form.filiale"
                                    name="filiale"
                                    placeholder="Nom de la filiale"
                                />
                                <InputError :message="form.errors.filiale" />
                            </div>

                            <div v-if="showProfilActuel" class="grid gap-2 md:col-span-2">
                                <Label for="profil_actuel">Profil actuel</Label>
                                <textarea
                                    id="profil_actuel"
                                    v-model.number="form.profil_actuel"
                                    name="profil_actuel"
                                    rows="3"
                                    class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Décrire le profil actuel du bénéficiaire"
                                ></textarea>
                                <InputError :message="form.errors.profil_actuel" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="profil_demande">Profil demandé</Label>
                                <textarea
                                    id="profil_demande"
                                    v-model.number="form.profil_demande"
                                    name="profil_demande"
                                    rows="3"
                                    class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Décrire le profil demandé"
                                ></textarea>
                                <InputError :message="form.errors.profil_demande" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="date_implementation_souhaitee">Date d'implémentation souhaitée</Label>
                                <Input
                                    id="date_implementation_souhaitee"
                                    v-model.number="form.date_implementation_souhaitee"
                                    type="date"
                                    name="date_implementation_souhaitee"
                                />
                                <InputError :message="form.errors.date_implementation_souhaitee" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Applications -->
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
                            <Label for="autre_application">Préciser l'application</Label>
                            <Input
                                id="autre_application"
                                v-model.number="form.autre_application"
                                name="autre_application"
                                placeholder="Nom de l'application"
                            />
                            <InputError :message="form.errors.autre_application" />
                        </div>
                    </div>

                    <!-- Section Type de profil -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Type de profil</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="type_profil">Type de profil</Label>
                                <select
                                    id="type_profil"
                                    v-model.number="form.type_profil"
                                    name="type_profil"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Sélectionner un type</option>
                                    <option value="Consultation simple">Consultation simple</option>
                                    <option value="Profil Specifique">Profil Spécifique</option>
                                </select>
                                <InputError :message="form.errors.type_profil" />
                            </div>

                            <div v-if="showProfilSpecifique" class="grid gap-2">
                                <Label for="profil_specifique">Profil spécifique *</Label>
                                <Input
                                    id="profil_specifique"
                                    v-model.number="form.profil_specifique"
                                    name="profil_specifique"
                                    placeholder="Décrire le profil spécifique"
                                    :required="showProfilSpecifique"
                                />
                                <InputError :message="form.errors.profil_specifique" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Période de validité -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Période de validité</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="periode_validite">Période de validité</Label>
                                <select
                                    id="periode_validite"
                                    v-model.number="form.periode_validite"
                                    name="periode_validite"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="Permanent">Permanent</option>
                                    <option value="Temporaire">Temporaire</option>
                                </select>
                                <InputError :message="form.errors.periode_validite" />
                            </div>

                            <div v-if="showDatesTemporaire" class="grid gap-2">
                                <Label for="date_debut">Date de début *</Label>
                                <Input
                                    id="date_debut"
                                    v-model.number="form.date_debut"
                                    type="date"
                                    name="date_debut"
                                    :required="showDatesTemporaire"
                                />
                                <InputError :message="form.errors.date_debut" />
                            </div>

                            <div v-if="showDatesTemporaire" class="grid gap-2">
                                <Label for="date_fin">Date de fin *</Label>
                                <Input
                                    id="date_fin"
                                    v-model.number="form.date_fin"
                                    type="date"
                                    name="date_fin"
                                    :required="showDatesTemporaire"
                                />
                                <InputError :message="form.errors.date_fin" />
                            </div>
                        </div>
                    </div>

                    <!-- Section Motif -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold border-b pb-2">Motif de la demande</h2>
                        
                        <div class="grid gap-2">
                            <Label for="motif_demande">Motif</Label>
                            <textarea
                                id="motif_demande"
                                v-model.number="form.motif_demande"
                                name="motif_demande"
                                rows="4"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Expliquer le motif de la demande d'habilitation"
                            ></textarea>
                            <InputError :message="form.errors.motif_demande" />
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
                            :disabled="processing || form.processing"
                        >
                            <span v-if="processing || form.processing">Enregistrement...</span>
                            <span v-else>Continuer vers l'étape 2</span>
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>

