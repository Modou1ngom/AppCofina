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
import { Code } from 'lucide-vue-next';
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
    profil: {
        id: number;
        matricule: string;
        prenom: string;
        nom: string;
        fonction?: string;
        departement?: string;
        email?: string;
        telephone?: string;
        site?: string;
        type_contrat: string;
        statut: string;
        n_plus_1_id?: number;
        n_plus_2_id?: number;
        };
    profils: Profil[];
    departements: Departement[];
    agences: Agence[];
    filiales?: Filiale[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profils',
        href: '/profils',
    },
    {
        title: 'Modifier le profil',
        href: '#',
    },
];

const form = useForm({
    nom: props.profil.nom,
    prenom: props.profil.prenom,
    matricule: props.profil.matricule,
    fonction: props.profil.fonction || '',
    departement: props.profil.departement || '',
    email: props.profil.email || '',
    telephone: props.profil.telephone || '',
    site: props.profil.site || '',
    type_contrat: props.profil.type_contrat as 'CDI' | 'CDD' | 'Stagiaire' | 'Autre',
    statut: props.profil.statut as 'actif' | 'inactif',
    n_plus_1_id: props.profil.n_plus_1_id || null,
});

// Filtrer les profils pour exclure le profil actuel
const availableProfils = computed(() => {
    return props.profils.filter(p => p.id !== props.profil.id);
});

// Filtrer les agences par filiale
const selectedFiliale = ref<number | null>(null);

// Déterminer la filiale de l'agence actuellement sélectionnée
const currentAgenceFiliale = computed(() => {
    if (!form.site) return null;
    const agence = props.agences.find(a => a.nom === form.site);
    return agence?.filiale_id || null;
});

// Initialiser selectedFiliale avec la filiale de l'agence actuelle
watch(() => props.agences, () => {
    if (currentAgenceFiliale.value) {
        selectedFiliale.value = currentAgenceFiliale.value;
    }
}, { immediate: true });

const filteredAgences = computed(() => {
    if (!selectedFiliale.value) {
        return props.agences;
    }
    return props.agences.filter(agence => agence.filiale_id === selectedFiliale.value);
});

// Réinitialiser l'agence sélectionnée si la filiale change et que l'agence n'appartient pas à la nouvelle filiale
watch(selectedFiliale, (newFilialeId) => {
    if (form.site) {
        const currentAgence = props.agences.find(a => a.nom === form.site);
        if (currentAgence && currentAgence.filiale_id !== newFilialeId) {
            form.site = '';
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
    form.put(`/profils/${props.profil.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier le profil" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier le profil</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="matricule" class="text-base font-medium text-gray-700">Matricule *</Label>
                        <Input
                            id="matricule"
                            v-model="form.matricule"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.matricule" />
                    </div>

                    <div>
                        <Label for="prenom" class="text-base font-medium text-gray-700">First Name *</Label>
                        <Input
                            id="prenom"
                            v-model="form.prenom"
                            type="text"
                            required
                            placeholder="John"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.prenom" />
                    </div>

                    <div>
                        <Label for="nom" class="text-base font-medium text-gray-700">Last Name *</Label>
                        <Input
                            id="nom"
                            v-model="form.nom"
                            type="text"
                            required
                            placeholder="Doe"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div>
                        <Label for="email" class="text-base font-medium text-gray-700">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="johndoe@email.com"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div>
                        <Label for="telephone" class="text-base font-medium text-gray-700">Phone</Label>
                        <Input
                            id="telephone"
                            v-model="form.telephone"
                            type="tel"
                            pattern="^(\+221|00221|221)?[0-9]{9}$"
                            placeholder="+221 XX XXX XX XX"
                            maxlength="20"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            @input="formatTelephone"
                        />
                        <InputError :message="form.errors.telephone" />
                    </div>

                    <div v-if="props.filiales && props.filiales.length > 0">
                        <Label for="filiale" class="text-base font-medium text-gray-700">Country</Label>
                        <select
                            id="filiale"
                            v-model="selectedFiliale"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
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
                    <div>
                        <Label for="site" class="text-base font-medium text-gray-700">Company</Label>
                        <select
                            id="site"
                            v-model="form.site"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
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
                        <p v-if="filteredAgences.length === 0 && selectedFiliale" class="mt-2 text-base text-gray-500">
                            Aucune agence trouvée pour la filiale sélectionnée.
                        </p>
                    </div>
                </FormSection>

                <FormSection title="Informations professionnelles" :columns="2" :show-code-icon="false">
                    <div>
                        <Label for="fonction" class="text-base font-medium text-gray-700">Fonction</Label>
                        <Input
                            id="fonction"
                            v-model="form.fonction"
                            type="text"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.fonction" />
                    </div>

                    <div>
                        <Label for="departement" class="text-base font-medium text-gray-700">Département</Label>
                        <select
                            id="departement"
                            v-model="form.departement"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
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
                        <Label for="type_contrat" class="text-base font-medium text-gray-700">Type de contrat</Label>
                        <select
                            id="type_contrat"
                            v-model="form.type_contrat"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="CDI">CDI</option>
                            <option value="CDD">CDD</option>
                            <option value="Stagiaire">Stagiaire</option>
                            <option value="Autre">Autre</option>
                        </select>
                        <InputError :message="form.errors.type_contrat" />
                    </div>

                    <div>
                        <Label for="statut" class="text-base font-medium text-gray-700">Statut</Label>
                        <select
                            id="statut"
                            v-model="form.statut"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                        <InputError :message="form.errors.statut" />
                    </div>

                    <div>
                        <Label for="n_plus_1" class="text-base font-medium text-gray-700">N+1</Label>
                        <select
                            id="n_plus_1"
                            v-model="form.n_plus_1_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option :value="null">Sélectionner un N+1</option>
                            <option
                                v-for="profil in availableProfils"
                                :key="profil.id"
                                :value="profil.id"
                            >
                                {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                            </option>
                        </select>
                        <InputError :message="form.errors.n_plus_1_id" />
                        
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/profils')">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

