<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { User, Mail, Phone, Globe, Building2, Briefcase, FileText, Users, ArrowLeft, ArrowRight } from 'lucide-vue-next';
import { computed, watch, ref } from 'vue';

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
}

interface Departement {
    id: number;
    nom: string;
    responsable_departement_id?: number | null;
    responsable?: {
        id: number;
        nom: string;
        prenom: string;
        matricule: string;
    } | null;
}

interface Agence {
    id: number;
    nom: string;
    filiale_id?: number | null;
}

interface Filiale {
    id: number;
    nom: string;
}

interface Props {
    profils: Profil[];
    departements: Departement[];
    agences: Agence[];
    filiales?: Filiale[];
    userFilialeId?: number | null;
    isSuperAdmin?: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profils',
        href: '/profils',
    },
    {
        title: 'Créer un profil',
        href: '#',
    },
];

const form = useForm({
    nom: '',
    prenom: '',
    fonction: '',
    departement: '',
    email: '',
    telephone: '',
    site: '',
    filiale_id: null as number | null,
    type_contrat: 'CDI' as 'CDI' | 'CDD' | 'Stagiaire' | 'Autre',
    statut: 'actif' as 'actif' | 'inactif',
    type_office: '' as '' | 'Back Office' | 'Front Office',
    n_plus_1_id: null as string | number | null,
});

// Initialiser la filiale avec celle de l'utilisateur (si admin/RH et pas super admin)
const selectedFiliale = ref<number | null>(
    props.userFilialeId && !props.isSuperAdmin ? props.userFilialeId : null
);

// Initialiser form.filiale_id avec la filiale de l'utilisateur
if (props.userFilialeId && !props.isSuperAdmin) {
    form.filiale_id = props.userFilialeId;
}

const filteredAgences = computed(() => {
    // Si une filiale est sélectionnée, filtrer les agences
    const filialeId = selectedFiliale.value || form.filiale_id;
    if (filialeId) {
        return props.agences.filter(agence => agence.filiale_id === filialeId);
    }
    return props.agences;
});

// Réinitialiser l'agence sélectionnée si la filiale change
watch(selectedFiliale, (newValue) => {
    form.site = '';
    form.filiale_id = newValue;
});

// Mettre à jour filiale_id quand une agence est sélectionnée
watch(() => form.site, (newSite) => {
    if (newSite) {
        const agence = props.agences.find(a => a.nom === newSite);
        if (agence && agence.filiale_id) {
            form.filiale_id = agence.filiale_id;
            selectedFiliale.value = agence.filiale_id;
        }
    }
});

// Formatage et validation du numéro de téléphone
const formatTelephone = (event: Event) => {
    const input = event.target as HTMLInputElement;
    let value = input.value.replace(/\D/g, ''); // Supprimer tous les caractères non numériques
    
    // Si commence par 221, garder le préfixe
    if (value.startsWith('221')) {
        value = '+221' + value.substring(3);
    } else if (value.startsWith('00221')) {
        value = '+221' + value.substring(5);
    } else if (value.length > 0 && !value.startsWith('+')) {
        // Si c'est un numéro local (commence par 7 ou 8), formater
        if (value.length <= 9) {
            value = value;
        } else {
            value = value.substring(0, 9);
        }
    }
    
    form.telephone = value;
};

const submit = () => {
    form.post('/profils', {
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
    <Head title="Créer un profil" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:gap-6 p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 sm:h-12 sm:w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg shrink-0">
                        <User class="h-5 w-5 sm:h-6 sm:w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Créer un profil</h1>
                        <p class="text-xs sm:text-sm text-gray-500 mt-1">Ajoutez un nouveau membre à votre équipe</p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4 sm:gap-6">
                <!-- Informations personnelles -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 shrink-0">
                            <User class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Informations personnelles</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <Label for="prenom" class="text-sm font-medium text-gray-700 mb-2 block">Prénom</Label>
                            <Input
                                id="prenom"
                                v-model="form.prenom"
                                type="text"
                                required
                                class="h-10 rounded-lg border-gray-300 focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/20"
                                placeholder="John"
                            />
                            <InputError :message="form.errors.prenom" />
                        </div>

                        <div>
                            <Label for="nom" class="text-sm font-medium text-gray-700 mb-2 block">Nom</Label>
                            <Input
                                id="nom"
                                v-model="form.nom"
                                type="text"
                                required
                                class="h-10 rounded-lg border-gray-300 focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/20"
                                placeholder="Doe"
                            />
                            <InputError :message="form.errors.nom" />
                        </div>

                        <div>
                            <Label for="email" class="text-sm font-medium text-gray-700 mb-2 block">
                                <div class="flex items-center gap-2">
                                    <Mail class="h-4 w-4 text-gray-400" />
                                    <span>Email</span>
                                </div>
                            </Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="h-10 rounded-lg border-gray-300 focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/20"
                                placeholder="johndoe@email.com"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div>
                            <Label for="telephone" class="text-sm font-medium text-gray-700 mb-2 block">
                                <div class="flex items-center gap-2">
                                    <Phone class="h-4 w-4 text-gray-400" />
                                    <span>Téléphone</span>
                                </div>
                            </Label>
                            <Input
                                id="telephone"
                                v-model="form.telephone"
                                type="tel"
                                pattern="^(\+221|00221|221)?[0-9]{9}$"
                                placeholder="+221 XX XXX XX XX"
                                maxlength="20"
                                class="h-10 rounded-lg border-gray-300 focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/20"
                                @input="formatTelephone"
                            />
                            <InputError :message="form.errors.telephone" />
                        </div>
                    </div>
                </div>

                <!-- Informations organisationnelles -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-green-50 text-green-600 shrink-0">
                            <Building2 class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Informations organisationnelles</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Champ filiale : masqué pour les admins/RH (sauf super admin) -->
                        <div v-if="props.filiales && props.filiales.length > 0 && props.isSuperAdmin">
                            <Label for="filiale" class="text-sm font-medium text-gray-700 mb-2 block">
                                <div class="flex items-center gap-2">
                                    <Globe class="h-4 w-4 text-gray-400" />
                                    <span>Filiale</span>
                                </div>
                            </Label>
                            <select
                                id="filiale"
                                v-model="selectedFiliale"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20"
                            >
                                <option :value="null">Toutes les filiales</option>
                                <option
                                    v-for="filiale in props.filiales"
                                    :key="filiale.id"
                                    :value="filiale.id"
                                >
                                    {{ filiale.nom }}
                                </option>
                            </select>
                        </div>
                        <!-- Affichage informatif pour les admins/RH -->
                        <div v-else-if="props.userFilialeId && !props.isSuperAdmin">
                            <Label class="text-sm font-medium text-gray-700 mb-2 block">
                                <div class="flex items-center gap-2">
                                    <Globe class="h-4 w-4 text-gray-400" />
                                    <span>Filiale</span>
                                </div>
                            </Label>
                            <div class="flex h-10 items-center rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700">
                                {{ props.filiales?.find(f => f.id === props.userFilialeId)?.nom || 'Filiale assignée' }}
                            </div>
                            <p class="mt-2 text-xs text-gray-500">La filiale est automatiquement assignée à celle de votre compte</p>
                        </div>
                        <div>
                            <Label for="site" class="text-sm font-medium text-gray-700 mb-2 block">
                                <div class="flex items-center gap-2">
                                    <Building2 class="h-4 w-4 text-gray-400" />
                                    <span>Agence</span>
                                </div>
                            </Label>
                            <select
                                id="site"
                                v-model="form.site"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20"
                            >
                                <option value="">Sélectionner une agence</option>
                                <option
                                    v-for="agence in filteredAgences"
                                    :key="agence.id"
                                    :value="agence.nom"
                                >
                                    {{ agence.nom }}
                                </option>
                            </select>
                            <InputError :message="form.errors.site" />
                            <p v-if="filteredAgences.length === 0 && selectedFiliale" class="mt-2 text-sm text-amber-600 bg-amber-50 p-2 rounded-lg">
                                Aucune agence trouvée pour la filiale sélectionnée.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Informations professionnelles -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                            <Briefcase class="h-5 w-5" />
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Informations professionnelles</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

                        <div>
                            <Label for="departement" class="text-sm font-medium text-gray-700 mb-2 block">Département</Label>
                            <select
                                id="departement"
                                v-model="form.departement"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                            >
                                <option value="">Sélectionner un département</option>
                                <option
                                    v-for="departement in props.departements"
                                    :key="departement.id"
                                    :value="departement.nom"
                                >
                                    {{ departement.nom }}
                                </option>
                            </select>
                            <InputError :message="form.errors.departement" />
                        </div>

                        <div>
                            <Label for="fonction" class="text-sm font-medium text-gray-700 mb-2 block">Fonction</Label>
                            <Input
                                id="fonction"
                                v-model="form.fonction"
                                type="text"
                                class="h-10 rounded-lg border-gray-300 focus-visible:border-purple-500 focus-visible:ring-2 focus-visible:ring-purple-500/20"
                                placeholder="Ex: Développeur, Manager..."
                            />
                            <InputError :message="form.errors.fonction" />
                        </div>

                        <div>
                            <Label for="type_contrat" class="text-sm font-medium text-gray-700 mb-2 block">
                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-gray-400" />
                                    <span>Type de contrat</span>
                                </div>
                            </Label>
                            <select
                                id="type_contrat"
                                v-model="form.type_contrat"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                            >
                                <option value="CDI">CDI</option>
                                <option value="CDD">CDD</option>
                                <option value="Stagiaire">Stagiaire</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <InputError :message="form.errors.type_contrat" />
                        </div>

                        <div>
                            <Label for="statut" class="text-sm font-medium text-gray-700 mb-2 block">Statut</Label>
                            <select
                                id="statut"
                                v-model="form.statut"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                            >
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                            <InputError :message="form.errors.statut" />
                        </div>

                        <div>
                            <Label for="type_office" class="text-sm font-medium text-gray-700 mb-2 block">Back/Front Office</Label>
                            <select
                                id="type_office"
                                v-model="form.type_office"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                            >
                                <option value="">Sélectionner un type</option>
                                <option value="Back Office">Back Office</option>
                                <option value="Front Office">Front Office</option>
                            </select>
                            <InputError :message="form.errors.type_office" />
                        </div>

                        <div>
                            <Label for="n_plus_1" class="text-sm font-medium text-gray-700 mb-2 block">
                                <div class="flex items-center gap-2">
                                    <Users class="h-4 w-4 text-gray-400" />
                                    <span>N+1</span>
                                </div>
                            </Label>
                            <select
                                id="n_plus_1"
                                v-model="form.n_plus_1_id"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                            >
                                <option :value="null">Sélectionner un N+1</option>
                                <option
                                    v-for="profil in props.profils"
                                    :key="profil.id"
                                    :value="profil.id"
                                >
                                    {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                </option>
                            </select>
                            <InputError :message="form.errors.n_plus_1_id" />
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 border-t border-gray-200 pt-4 sm:pt-6">
                    <Button 
                        type="button" 
                        variant="outline" 
                        @click="router.visit('/profils')"
                        class="w-full sm:w-auto h-11 px-6 rounded-lg border-gray-300 hover:bg-gray-50"
                    >
                        Annuler
                    </Button>
                    <Button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full sm:w-auto h-11 px-8 rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white shadow-lg hover:shadow-xl transition-all disabled:opacity-50"
                    >
                        <span v-if="form.processing">Création en cours...</span>
                        <span v-else class="flex items-center gap-2">
                            <User class="h-4 w-4" />
                            Créer le profil
                        </span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

