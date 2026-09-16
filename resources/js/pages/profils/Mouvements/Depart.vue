<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import ProfilSearchSelect from '@/components/ProfilSearchSelect.vue';
import { computed } from 'vue';

interface StaffOption {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
    fonction?: string | null;
    departement?: string | null;
    site?: string | null;
}

interface Props {
    profils: StaffOption[];
    profilIdInitial?: number | null;
    motifs: string[];
}

const props = withDefaults(defineProps<Props>(), {
    profilIdInitial: null,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enrôlement staff', href: '/profils' },
    { title: 'Mouvements', href: '/profils/mouvements' },
    { title: 'Déclarer un départ', href: '#' },
];

const form = useForm({
    profil_id: props.profilIdInitial,
    date_effet: new Date().toISOString().slice(0, 10),
    motif: '',
    motif_libre: '',
});

const selected = computed(() => props.profils.find((p) => p.id === form.profil_id) ?? null);
const showMotifLibre = computed(() => form.motif === 'Autre');

const submit = () => {
    form.post('/profils/mouvements/depart');
};
</script>

<template>
    <Head title="Déclarer un départ" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Déclarer un départ</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Le collaborateur passe inactif, son compte de connexion est désactivé, et le mouvement est historisé.
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
                    <p><span class="font-medium">Poste actuel :</span> {{ selected.fonction || '—' }}</p>
                    <p><span class="font-medium">Département :</span> {{ selected.departement || '—' }}</p>
                    <p><span class="font-medium">Agence :</span> {{ selected.site || '—' }}</p>
                </div>

                <div>
                    <Label for="date_effet" class="mb-2 block text-sm font-medium text-gray-700">Date de départ *</Label>
                    <Input id="date_effet" v-model="form.date_effet" type="date" class="h-10 border-gray-300" />
                    <InputError :message="form.errors.date_effet" />
                </div>

                <div>
                    <Label for="motif" class="mb-2 block text-sm font-medium text-gray-700">Motif *</Label>
                    <select
                        id="motif"
                        v-model="form.motif"
                        class="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm"
                    >
                        <option value="">Sélectionner un motif</option>
                        <option v-for="motif in motifs" :key="motif" :value="motif">{{ motif }}</option>
                    </select>
                    <InputError :message="form.errors.motif" />
                </div>

                <div v-if="showMotifLibre">
                    <Label for="motif_libre" class="mb-2 block text-sm font-medium text-gray-700">Précisez le motif *</Label>
                    <Input id="motif_libre" v-model="form.motif_libre" type="text" class="h-10 border-gray-300" />
                    <InputError :message="form.errors.motif_libre" />
                </div>

                <div class="flex justify-end gap-2">
                    <Link href="/profils/mouvements">
                        <Button type="button" variant="outline">Annuler</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" class="bg-red-600 hover:bg-red-700">
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer le départ' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
