<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import ProfilSearchSelect from '@/components/ProfilSearchSelect.vue';
import ProfilSignatureSelector from '@/components/ProfilSignatureSelector.vue';
import { User, Mail, Phone, Building2, Briefcase, FileText, Users, ArrowLeft, IdCard, PenLine } from 'lucide-vue-next';
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
        type_office?: string;
        n_plus_1_id?: number;
        n_plus_2_id?: number;
        date_entree?: string | null;
        date_sortie?: string | null;
        motif_depart?: string | null;
        created_at?: string | null;
        matricule_sirh?: string | null;
        entite?: string | null;
        nationalite?: string | null;
        genre?: string | null;
        date_naissance?: string | null;
        diplome?: string | null;
        age?: number | null;
        situation_matrimoniale?: string | null;
        nombre_enfants?: number | null;
        numero_cni?: string | null;
        categorie?: string | null;
        duree_contrat?: string | null;
        date_debut_contrat?: string | null;
        date_fin_contrat?: string | null;
        date_embauche?: string | null;
        dossier_a_jour?: boolean | null;
        anciennete?: string | null;
        grade?: string | null;
        h?: string | null;
        numero_carte_assurance?: string | null;
        signature?: string | null;
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
    type_contrat: (props.profil.type_contrat || '') as '' | 'CDI' | 'CDD' | 'Stagiaire' | 'Autre',
    statut: props.profil.statut as 'actif' | 'inactif',
    type_office: (props.profil.type_office || '') as '' | 'Back Office' | 'Front Office',
    n_plus_1_id: props.profil.n_plus_1_id || null,
    date_entree: (props.profil.date_entree || props.profil.created_at)
        ? String(props.profil.date_entree || props.profil.created_at).slice(0, 10)
        : '',
    matricule_sirh: props.profil.matricule_sirh || '',
    entite: props.profil.entite || '',
    nationalite: props.profil.nationalite || '',
    genre: (props.profil.genre || '') as '' | 'Homme' | 'Femme',
    date_naissance: props.profil.date_naissance ? String(props.profil.date_naissance).slice(0, 10) : '',
    diplome: props.profil.diplome || '',
    age: props.profil.age ?? '',
    situation_matrimoniale: props.profil.situation_matrimoniale || '',
    nombre_enfants: props.profil.nombre_enfants ?? '',
    numero_cni: props.profil.numero_cni || '',
    categorie: props.profil.categorie || '',
    duree_contrat: props.profil.duree_contrat || '',
    date_debut_contrat: props.profil.date_debut_contrat ? String(props.profil.date_debut_contrat).slice(0, 10) : '',
    date_fin_contrat: props.profil.date_fin_contrat ? String(props.profil.date_fin_contrat).slice(0, 10) : '',
    date_embauche: props.profil.date_embauche ? String(props.profil.date_embauche).slice(0, 10) : '',
    dossier_a_jour: props.profil.dossier_a_jour === null || props.profil.dossier_a_jour === undefined
        ? ''
        : (props.profil.dossier_a_jour ? '1' : '0'),
    anciennete: props.profil.anciennete || '',
    date_sortie: props.profil.date_sortie ? String(props.profil.date_sortie).slice(0, 10) : '',
    motif_depart: props.profil.motif_depart || '',
    grade: props.profil.grade || '',
    h: props.profil.h || '',
    numero_carte_assurance: props.profil.numero_carte_assurance || '',
    signature: props.profil.signature ?? '',
    replace_signature: false,
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
    const raw = input.value;
    const digits = raw.replace(/\D/g, '');

    if (raw.includes('/') || digits.length > 9) {
        form.telephone = raw.slice(0, 64);

        return;
    }

    let value = digits;

    if (value.startsWith('221')) {
        value = '+221' + value.substring(3);
    } else if (value.startsWith('00221')) {
        value = '+221' + value.substring(5);
    }

    form.telephone = value;
};

const inputClass =
    'h-11 rounded-xl border-gray-200 bg-white shadow-sm transition-all focus-visible:border-primary/40 focus-visible:ring-2 focus-visible:ring-primary/15';

const selectClass =
    'flex h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition-all focus:border-primary/40 focus:ring-2 focus:ring-primary/15';

const ageCalcule = computed(() => {
    if (!form.date_naissance) {
        return form.age === '' || form.age === null ? null : Number(form.age);
    }

    const birth = new Date(`${form.date_naissance}T00:00:00`);
    if (Number.isNaN(birth.getTime())) {
        return null;
    }

    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const month = today.getMonth() - birth.getMonth();
    if (month < 0 || (month === 0 && today.getDate() < birth.getDate())) {
        age -= 1;
    }

    return age >= 0 ? age : null;
});

const ancienneteCalculee = computed(() => {
    const raw = form.date_embauche || form.date_entree;
    if (!raw) {
        return form.anciennete || '';
    }

    const start = new Date(`${raw}T00:00:00`);
    if (Number.isNaN(start.getTime())) {
        return form.anciennete || '';
    }

    const now = new Date();
    let years = now.getFullYear() - start.getFullYear();
    let months = now.getMonth() - start.getMonth();
    if (now.getDate() < start.getDate()) {
        months -= 1;
    }
    if (months < 0) {
        years -= 1;
        months += 12;
    }
    if (years < 0) {
        return '';
    }

    const parts: string[] = [];
    if (years > 0) {
        parts.push(`${years} an${years > 1 ? 's' : ''}`);
    }
    if (months > 0) {
        parts.push(`${months} mois`);
    }

    return parts.length ? parts.join(' ') : 'Moins d’un mois';
});

watch(() => form.date_naissance, () => {
    if (form.date_naissance && ageCalcule.value !== null) {
        form.age = ageCalcule.value;
    }
});

const submit = () => {
    form.put(`/profils/${props.profil.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier le profil" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-red-500 to-red-600 text-white shadow-lg shadow-red-500/20">
                            <User class="h-7 w-7" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">{{ profil.prenom }} {{ profil.nom }}</h1>
                            <p class="mt-1 text-sm text-gray-500">Mise à jour de la fiche collaborateur · {{ profil.matricule }}</p>
                        </div>
                    </div>
                    <Button type="button" variant="outline" class="rounded-xl border-gray-200 bg-white shadow-sm" @click="router.visit('/profils')">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour à la liste
                    </Button>
                </div>

                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                        <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">1</span>
                            <User class="h-5 w-5 text-gray-400" />
                            <div>
                                <h2 class="font-semibold text-gray-900">Identité</h2>
                                <p class="text-xs text-gray-500">État civil et informations personnelles</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
                            <div>
                                <Label for="matricule" class="mb-2 block text-sm font-medium text-gray-700">Matricule <span class="text-primary">*</span></Label>
                                <Input id="matricule" v-model="form.matricule" type="text" required :class="inputClass" />
                                <InputError :message="form.errors.matricule" />
                            </div>
                            <div>
                                <Label for="prenom" class="mb-2 block text-sm font-medium text-gray-700">Prénom <span class="text-primary">*</span></Label>
                                <Input id="prenom" v-model="form.prenom" type="text" required :class="inputClass" />
                                <InputError :message="form.errors.prenom" />
                            </div>
                            <div>
                                <Label for="nom" class="mb-2 block text-sm font-medium text-gray-700">Nom <span class="text-primary">*</span></Label>
                                <Input id="nom" v-model="form.nom" type="text" required :class="inputClass" />
                                <InputError :message="form.errors.nom" />
                            </div>
                            <div>
                                <Label for="genre" class="mb-2 block text-sm font-medium text-gray-700">Genre</Label>
                                <select id="genre" v-model="form.genre" :class="selectClass">
                                    <option value="">Sélectionner</option>
                                    <option value="Homme">Homme</option>
                                    <option value="Femme">Femme</option>
                                </select>
                                <InputError :message="form.errors.genre" />
                            </div>
                            <div>
                                <Label for="nationalite" class="mb-2 block text-sm font-medium text-gray-700">Nationalité</Label>
                                <Input id="nationalite" v-model="form.nationalite" type="text" :class="inputClass" />
                                <InputError :message="form.errors.nationalite" />
                            </div>
                            <div>
                                <Label for="date_naissance" class="mb-2 block text-sm font-medium text-gray-700">Date de naissance</Label>
                                <Input id="date_naissance" v-model="form.date_naissance" type="date" :class="inputClass" />
                                <InputError :message="form.errors.date_naissance" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-sm font-medium text-gray-700">Âge</Label>
                                <div class="flex h-11 items-center rounded-xl border border-dashed border-gray-200 bg-slate-50 px-3 text-sm text-gray-700">
                                    {{ ageCalcule !== null ? `${ageCalcule} ans` : 'Calculé depuis la date de naissance' }}
                                </div>
                            </div>
                            <div>
                                <Label for="diplome" class="mb-2 block text-sm font-medium text-gray-700">Diplôme</Label>
                                <Input id="diplome" v-model="form.diplome" type="text" :class="inputClass" />
                                <InputError :message="form.errors.diplome" />
                            </div>
                            <div>
                                <Label for="situation_matrimoniale" class="mb-2 block text-sm font-medium text-gray-700">Situation matrimoniale</Label>
                                <select id="situation_matrimoniale" v-model="form.situation_matrimoniale" :class="selectClass">
                                    <option value="">Sélectionner</option>
                                    <option
                                        v-if="form.situation_matrimoniale && !['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf(ve)'].includes(form.situation_matrimoniale)"
                                        :value="form.situation_matrimoniale"
                                    >{{ form.situation_matrimoniale }}</option>
                                    <option value="Célibataire">Célibataire</option>
                                    <option value="Marié(e)">Marié(e)</option>
                                    <option value="Divorcé(e)">Divorcé(e)</option>
                                    <option value="Veuf(ve)">Veuf(ve)</option>
                                </select>
                                <InputError :message="form.errors.situation_matrimoniale" />
                            </div>
                            <div>
                                <Label for="nombre_enfants" class="mb-2 block text-sm font-medium text-gray-700">Nombre d'enfants</Label>
                                <Input id="nombre_enfants" v-model="form.nombre_enfants" type="number" min="0" :class="inputClass" />
                                <InputError :message="form.errors.nombre_enfants" />
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                        <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">2</span>
                            <IdCard class="h-5 w-5 text-gray-400" />
                            <div>
                                <h2 class="font-semibold text-gray-900">Coordonnées et pièces</h2>
                                <p class="text-xs text-gray-500">Contact, identité et couverture</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2 sm:p-6">
                            <div>
                                <Label for="email" class="mb-2 block text-sm font-medium text-gray-700">Adresse e-mail</Label>
                                <div class="relative">
                                    <Mail class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input id="email" v-model="form.email" type="email" :class="[inputClass, 'pl-10']" />
                                </div>
                                <InputError :message="form.errors.email" />
                            </div>
                            <div>
                                <Label for="telephone" class="mb-2 block text-sm font-medium text-gray-700">Téléphone</Label>
                                <div class="relative">
                                    <Phone class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input id="telephone" v-model="form.telephone" type="tel" maxlength="64" :class="[inputClass, 'pl-10']" @input="formatTelephone" />
                                </div>
                                <InputError :message="form.errors.telephone" />
                            </div>
                            <div>
                                <Label for="numero_cni" class="mb-2 block text-sm font-medium text-gray-700">N° CNI</Label>
                                <Input id="numero_cni" v-model="form.numero_cni" type="text" :class="inputClass" />
                                <InputError :message="form.errors.numero_cni" />
                            </div>
                            <div>
                                <Label for="numero_carte_assurance" class="mb-2 block text-sm font-medium text-gray-700">N° carte assurance</Label>
                                <Input id="numero_carte_assurance" v-model="form.numero_carte_assurance" type="text" :class="inputClass" />
                                <InputError :message="form.errors.numero_carte_assurance" />
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                        <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">3</span>
                            <Building2 class="h-5 w-5 text-gray-400" />
                            <div>
                                <h2 class="font-semibold text-gray-900">Affectation</h2>
                                <p class="text-xs text-gray-500">Entité, site, fonction et hiérarchie</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
                            <div>
                                <Label for="entite" class="mb-2 block text-sm font-medium text-gray-700">Entité</Label>
                                <Input id="entite" v-model="form.entite" type="text" :class="inputClass" />
                                <InputError :message="form.errors.entite" />
                            </div>
                            <div v-if="props.filiales && props.filiales.length > 0">
                                <Label for="filiale" class="mb-2 block text-sm font-medium text-gray-700">Filiale</Label>
                                <select id="filiale" v-model="selectedFiliale" :class="selectClass">
                                    <option :value="null">Toutes les filiales</option>
                                    <option v-for="filiale in props.filiales" :key="filiale.id" :value="filiale.id">{{ filiale.nom }}</option>
                                </select>
                            </div>
                            <div>
                                <Label for="site" class="mb-2 block text-sm font-medium text-gray-700">Site</Label>
                                <select id="site" v-model="form.site" :class="selectClass">
                                    <option value="">Sélectionner une agence</option>
                                    <option v-for="agence in filteredAgences" :key="agence.id" :value="agence.nom">{{ agence.nom }}</option>
                                </select>
                                <InputError :message="form.errors.site" />
                            </div>
                            <div>
                                <Label for="departement" class="mb-2 block text-sm font-medium text-gray-700">Département</Label>
                                <select id="departement" v-model="form.departement" :class="selectClass">
                                    <option value="">Sélectionner un département</option>
                                    <option v-for="departement in props.departements" :key="departement.id" :value="departement.nom">{{ departement.nom }}</option>
                                </select>
                                <InputError :message="form.errors.departement" />
                            </div>
                            <div>
                                <Label for="fonction" class="mb-2 block text-sm font-medium text-gray-700">Fonction</Label>
                                <Input id="fonction" v-model="form.fonction" type="text" :class="inputClass" />
                                <InputError :message="form.errors.fonction" />
                            </div>
                            <div>
                                <Label for="categorie" class="mb-2 block text-sm font-medium text-gray-700">Catégorie</Label>
                                <Input id="categorie" v-model="form.categorie" type="text" :class="inputClass" />
                                <InputError :message="form.errors.categorie" />
                            </div>
                            <div>
                                <Label for="grade" class="mb-2 block text-sm font-medium text-gray-700">Grade</Label>
                                <Input id="grade" v-model="form.grade" type="text" :class="inputClass" />
                                <InputError :message="form.errors.grade" />
                            </div>
                            <div>
                                <Label for="h" class="mb-2 block text-sm font-medium text-gray-700">H</Label>
                                <Input id="h" v-model="form.h" type="text" :class="inputClass" />
                                <InputError :message="form.errors.h" />
                            </div>
                            <div>
                                <Label for="type_office" class="mb-2 block text-sm font-medium text-gray-700">Front / Back</Label>
                                <select id="type_office" v-model="form.type_office" :class="selectClass">
                                    <option value="">Sélectionner</option>
                                    <option value="Front Office">Front Office</option>
                                    <option value="Back Office">Back Office</option>
                                </select>
                                <InputError :message="form.errors.type_office" />
                            </div>
                            <div class="sm:col-span-2 xl:col-span-2">
                                <Label for="n_plus_1" class="mb-2 flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <Users class="h-4 w-4 text-gray-400" />
                                    N+1
                                </Label>
                                <ProfilSearchSelect
                                    id="n_plus_1"
                                    v-model="form.n_plus_1_id"
                                    :profils="availableProfils"
                                    :exclude-id="profil.id"
                                    clear-option-label="Aucun N+1"
                                    input-class="h-11 rounded-xl border-gray-200 shadow-sm"
                                />
                                <InputError :message="form.errors.n_plus_1_id" />
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                        <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">4</span>
                            <Briefcase class="h-5 w-5 text-gray-400" />
                            <div>
                                <h2 class="font-semibold text-gray-900">Contrat</h2>
                                <p class="text-xs text-gray-500">Dates, durée, départ et suivi du dossier</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
                            <div>
                                <Label for="matricule_sirh" class="mb-2 block text-sm font-medium text-gray-700">Matricule SIRH</Label>
                                <Input id="matricule_sirh" v-model="form.matricule_sirh" type="text" :class="inputClass" />
                                <InputError :message="form.errors.matricule_sirh" />
                            </div>
                            <div>
                                <Label for="type_contrat" class="mb-2 flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <FileText class="h-4 w-4 text-gray-400" />
                                    Type de contrat
                                </Label>
                                <select id="type_contrat" v-model="form.type_contrat" :class="selectClass">
                                    <option value="">Non renseigné</option>
                                    <option value="CDI">CDI</option>
                                    <option value="CDD">CDD</option>
                                    <option value="Stagiaire">Stagiaire</option>
                                    <option value="Autre">Autre</option>
                                </select>
                                <InputError :message="form.errors.type_contrat" />
                            </div>
                            <div>
                                <Label for="duree_contrat" class="mb-2 block text-sm font-medium text-gray-700">Durée</Label>
                                <Input id="duree_contrat" v-model="form.duree_contrat" type="text" :class="inputClass" />
                                <InputError :message="form.errors.duree_contrat" />
                            </div>
                            <div>
                                <Label for="date_debut_contrat" class="mb-2 block text-sm font-medium text-gray-700">Début de contrat</Label>
                                <Input id="date_debut_contrat" v-model="form.date_debut_contrat" type="date" :class="inputClass" />
                                <InputError :message="form.errors.date_debut_contrat" />
                            </div>
                            <div>
                                <Label for="date_fin_contrat" class="mb-2 block text-sm font-medium text-gray-700">Fin de contrat</Label>
                                <Input id="date_fin_contrat" v-model="form.date_fin_contrat" type="date" :class="inputClass" />
                                <InputError :message="form.errors.date_fin_contrat" />
                            </div>
                            <div>
                                <Label for="date_embauche" class="mb-2 block text-sm font-medium text-gray-700">Date d'embauche</Label>
                                <Input id="date_embauche" v-model="form.date_embauche" type="date" :class="inputClass" />
                                <InputError :message="form.errors.date_embauche" />
                            </div>
                            <div>
                                <Label for="date_entree" class="mb-2 block text-sm font-medium text-gray-700">Entrée dans l'établissement</Label>
                                <Input id="date_entree" v-model="form.date_entree" type="date" :class="inputClass" />
                                <InputError :message="form.errors.date_entree" />
                            </div>
                            <div>
                                <Label for="statut" class="mb-2 block text-sm font-medium text-gray-700">Statut</Label>
                                <select id="statut" v-model="form.statut" :class="selectClass">
                                    <option value="actif">Actif</option>
                                    <option value="inactif">Inactif</option>
                                </select>
                                <InputError :message="form.errors.statut" />
                            </div>
                            <div>
                                <Label for="dossier_a_jour" class="mb-2 block text-sm font-medium text-gray-700">Dossier à jour</Label>
                                <select id="dossier_a_jour" v-model="form.dossier_a_jour" :class="selectClass">
                                    <option value="">Non renseigné</option>
                                    <option value="1">Oui</option>
                                    <option value="0">Non</option>
                                </select>
                                <InputError :message="form.errors.dossier_a_jour" />
                            </div>
                            <div>
                                <Label for="date_sortie" class="mb-2 block text-sm font-medium text-gray-700">Date de départ</Label>
                                <Input id="date_sortie" v-model="form.date_sortie" type="date" :class="inputClass" />
                                <InputError :message="form.errors.date_sortie" />
                            </div>
                            <div>
                                <Label for="motif_depart" class="mb-2 block text-sm font-medium text-gray-700">Motifs</Label>
                                <Input id="motif_depart" v-model="form.motif_depart" type="text" :class="inputClass" />
                                <InputError :message="form.errors.motif_depart" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-sm font-medium text-gray-700">Ancienneté</Label>
                                <div class="flex h-11 items-center rounded-xl border border-dashed border-gray-200 bg-slate-50 px-3 text-sm text-gray-700">
                                    {{ ancienneteCalculee || 'Calculée depuis la date d’embauche ou d’entrée' }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                        <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">5</span>
                            <PenLine class="h-5 w-5 text-gray-400" />
                            <div>
                                <h2 class="font-semibold text-gray-900">Signature</h2>
                                <p class="text-xs text-gray-500">Réutilisée pour les validations d’habilitation</p>
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <ProfilSignatureSelector
                                v-model="form.signature"
                                :stored-signature="profil.signature"
                                :width="400"
                                :height="160"
                            />
                            <div v-if="profil.signature" class="mt-4 flex items-center gap-2">
                                <Checkbox id="replace_signature" v-model:checked="form.replace_signature" />
                                <Label for="replace_signature" class="cursor-pointer text-sm">Remplacer la signature existante</Label>
                            </div>
                            <InputError :message="form.errors.signature" />
                        </div>
                    </section>

                    <div class="sticky bottom-4 z-10 flex flex-col-reverse gap-3 rounded-2xl border border-gray-200/80 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-end">
                        <Button type="button" variant="outline" class="h-11 rounded-xl border-gray-200" @click="router.visit('/profils')">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="form.processing" class="h-11 rounded-xl bg-gradient-to-r from-red-600 to-red-700 px-8 text-white shadow-lg shadow-red-600/20 hover:from-red-700 hover:to-red-800">
                            {{ form.processing ? 'Mise à jour…' : 'Enregistrer la fiche' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
