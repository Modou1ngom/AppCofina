<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import ProfilSearchSelect from '@/components/ProfilSearchSelect.vue';
import { computed, watch } from 'vue';

interface StaffOption {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
    fonction?: string | null;
    departement?: string | null;
    site?: string | null;
    n_plus_1_id?: number | null;
}

interface Departement {
    id: number;
    nom: string;
}

interface Agence {
    id: number;
    nom: string;
}

interface Props {
    profils: StaffOption[];
    hierarchie: { id: number; nom: string; prenom: string; matricule: string }[];
    departements: Departement[];
    agences: Agence[];
    profilIdInitial?: number | null;
    motifs: string[];
}

const props = withDefaults(defineProps<Props>(), {
    profilIdInitial: null,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enrôlement staff', href: '/profils' },
    { title: 'Mouvements', href: '/profils/mouvements' },
    { title: 'Changement de poste', href: '#' },
];

const form = useForm({
    profil_id: props.profilIdInitial,
    date_effet: new Date().toISOString().slice(0, 10),
    fonction: '',
    departement: '',
    site: '',
    n_plus_1_id: null as number | null,
    motif: '',
    motif_libre: '',
});

const selected = computed(() => props.profils.find((p) => p.id === form.profil_id) ?? null);
const showMotifLibre = computed(() => form.motif === 'Autre');

const prefillFromStaff = (staff: StaffOption | null) => {
    if (!staff) {
        return;
    }
    form.fonction = staff.fonction || '';
    form.departement = staff.departement || '';
    form.site = staff.site || '';
    form.n_plus_1_id = staff.n_plus_1_id ?? null;
};

watch(
    () => form.profil_id,
    (id) => {
        prefillFromStaff(props.profils.find((p) => p.id === id) ?? null);
    },
    { immediate: true },
);

const submit = () => {
    form.post('/profils/mouvements/changement-poste');
};
</script>

<template>
    <Head title="Changement de poste" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Changement de poste</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Mettre à jour la fonction, le département, l’agence ou le N+1. L’historique est conservé.
                </p>
            </div>

            <form class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <Label class="mb-2 block text-sm font-medium text-gray-700">Collaborateur *</Label>
                    <ProfilSearchSelect
                        v-model="form.profil_id"
                        :profils="profils"
                        :clear-option-label="false"
                        placeholder="Rechercher un staff actif…"
                    />
                    <InputError :message="form.errors.profil_id" />
                </div>

                <div v-if="selected" class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm text-gray-700">
                    <p class="font-medium text-gray-900">Situation actuelle</p>
                    <p>Fonction : {{ selected.fonction || '—' }}</p>
                    <p>Département : {{ selected.departement || '—' }}</p>
                    <p>Agence : {{ selected.site || '—' }}</p>
                </div>

                <div>
                    <Label for="date_effet" class="mb-2 block text-sm font-medium text-gray-700">Date d’effet *</Label>
                    <Input id="date_effet" v-model="form.date_effet" type="date" class="h-10 border-gray-300" />
                    <InputError :message="form.errors.date_effet" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label for="fonction" class="mb-2 block text-sm font-medium text-gray-700">Nouvelle fonction</Label>
                        <Input id="fonction" v-model="form.fonction" type="text" class="h-10 border-gray-300" />
                        <InputError :message="form.errors.fonction" />
                    </div>
                    <div>
                        <Label for="departement" class="mb-2 block text-sm font-medium text-gray-700">Nouveau département</Label>
                        <select
                            id="departement"
                            v-model="form.departement"
                            class="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm"
                        >
                            <option value="">Sélectionner</option>
                            <option v-for="dept in departements" :key="dept.id" :value="dept.nom">{{ dept.nom }}</option>
                        </select>
                        <InputError :message="form.errors.departement" />
                    </div>
                    <div>
                        <Label for="site" class="mb-2 block text-sm font-medium text-gray-700">Nouvelle agence</Label>
                        <select
                            id="site"
                            v-model="form.site"
                            class="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm"
                        >
                            <option value="">Sélectionner</option>
                            <option v-for="agence in agences" :key="agence.id" :value="agence.nom">{{ agence.nom }}</option>
                        </select>
                        <InputError :message="form.errors.site" />
                    </div>
                    <div>
                        <Label class="mb-2 block text-sm font-medium text-gray-700">Nouveau N+1</Label>
                        <ProfilSearchSelect
                            v-model="form.n_plus_1_id"
                            :profils="hierarchie"
                            :exclude-id="form.profil_id"
                            clear-option-label="Aucun N+1"
                        />
                        <InputError :message="form.errors.n_plus_1_id" />
                    </div>
                </div>

                <div>
                    <Label for="motif" class="mb-2 block text-sm font-medium text-gray-700">Motif</Label>
                    <select
                        id="motif"
                        v-model="form.motif"
                        class="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm"
                    >
                        <option value="">Optionnel</option>
                        <option v-for="motif in motifs" :key="motif" :value="motif">{{ motif }}</option>
                    </select>
                    <InputError :message="form.errors.motif" />
                </div>

                <div v-if="showMotifLibre">
                    <Label for="motif_libre" class="mb-2 block text-sm font-medium text-gray-700">Précisez le motif</Label>
                    <Input id="motif_libre" v-model="form.motif_libre" type="text" class="h-10 border-gray-300" />
                    <InputError :message="form.errors.motif_libre" />
                </div>

                <div class="flex justify-end gap-2">
                    <Link href="/profils/mouvements">
                        <Button type="button" variant="outline">Annuler</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700">
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer le changement' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
