<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { computed, watch } from 'vue';

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
    n_plus_2_id: props.profil.n_plus_2_id || null,
});

// Filtrer les profils pour exclure le profil actuel
const availableProfils = computed(() => {
    return props.profils.filter(p => p.id !== props.profil.id);
});

// Filtrer les profils disponibles pour N+1 (exclure celui sélectionné comme N+2 et le profil actuel)
const availableN1 = computed(() => {
    return props.profils.filter(p => {
        if (p.id === props.profil.id) return false;
        if (form.n_plus_2_id && p.id == form.n_plus_2_id) return false;
        return true;
    });
});

// Filtrer les profils disponibles pour N+2 (exclure celui sélectionné comme N+1 et le profil actuel)
const availableN2 = computed(() => {
    return props.profils.filter(p => {
        if (p.id === props.profil.id) return false;
        if (form.n_plus_1_id && p.id == form.n_plus_1_id) return false;
        return true;
    });
});

// Réinitialiser N+2 si N+1 est sélectionné comme N+2
watch(() => form.n_plus_1_id, (newValue) => {
    if (newValue && form.n_plus_2_id == newValue) {
        form.n_plus_2_id = null;
    }
});

// Réinitialiser N+1 si N+2 est sélectionné comme N+1
watch(() => form.n_plus_2_id, (newValue) => {
    if (newValue && form.n_plus_1_id == newValue) {
        form.n_plus_1_id = null;
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
            <h1 class="text-2xl font-bold">Modifier le profil</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations personnelles</h2>
                    
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="matricule">Matricule *</Label>
                            <Input
                                id="matricule"
                                v-model="form.matricule"
                                type="text"
                                required
                                class="mt-1"
                            />
                            <InputError :message="form.errors.matricule" />
                        </div>

                        <div>
                            <Label for="prenom">Prénom *</Label>
                            <Input
                                id="prenom"
                                v-model="form.prenom"
                                type="text"
                                required
                                class="mt-1"
                            />
                            <InputError :message="form.errors.prenom" />
                        </div>

                        <div>
                            <Label for="nom">Nom *</Label>
                            <Input
                                id="nom"
                                v-model="form.nom"
                                type="text"
                                required
                                class="mt-1"
                            />
                            <InputError :message="form.errors.nom" />
                        </div>

                        <div>
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div>
                            <Label for="telephone">Téléphone</Label>
                            <Input
                                id="telephone"
                                v-model="form.telephone"
                                type="tel"
                                pattern="^(\+221|00221|221)?[0-9]{9}$"
                                placeholder="+221 XX XXX XX XX "
                                maxlength="20"
                                class="mt-1"
                                @input="formatTelephone"
                            />
                         
                            <InputError :message="form.errors.telephone" />
                        </div>

                        <div>
                            <Label for="site">Agence</Label>
                            <select
                                id="site"
                                v-model="form.site"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="">Sélectionner une agence</option>
                                <option
                                    v-for="agence in props.agences"
                                    :key="agence.id"
                                    :value="agence.nom"
                                >
                                    {{ agence.nom }}
                                </option>
                            </select>
                            <InputError :message="form.errors.site" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations professionnelles</h2>
                    
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="fonction">Fonction</Label>
                            <Input
                                id="fonction"
                                v-model="form.fonction"
                                type="text"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.fonction" />
                        </div>

                        <div>
                            <Label for="departement">Département</Label>
                            <select
                                id="departement"
                                v-model="form.departement"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
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
                            <Label for="type_contrat">Type de contrat</Label>
                            <select
                                id="type_contrat"
                                v-model="form.type_contrat"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="CDI">CDI</option>
                                <option value="CDD">CDD</option>
                                <option value="Stagiaire">Stagiaire</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <InputError :message="form.errors.type_contrat" />
                        </div>

                        <div>
                            <Label for="statut">Statut</Label>
                            <select
                                id="statut"
                                v-model="form.statut"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                            <InputError :message="form.errors.statut" />
                        </div>

                        <div>
                            <Label for="n_plus_1">N+1</Label>
                            <select
                                id="n_plus_1"
                                v-model="form.n_plus_1_id"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option :value="null">Sélectionner un N+1</option>
                                <option
                                    v-for="profil in availableN1"
                                    :key="profil.id"
                                    :value="profil.id"
                                >
                                    {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                </option>
                            </select>
                            <InputError :message="form.errors.n_plus_1_id" />
                        </div>

                        <div>
                            <Label for="n_plus_2">N+2</Label>
                            <select
                                id="n_plus_2"
                                v-model="form.n_plus_2_id"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option :value="null">Sélectionner un N+2</option>
                                <option
                                    v-for="profil in availableN2"
                                    :key="profil.id"
                                    :value="profil.id"
                                >
                                    {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                </option>
                            </select>
                            <InputError :message="form.errors.n_plus_2_id" />
                        </div>
                    </div>
                </div>

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

