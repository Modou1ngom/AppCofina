<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Code } from 'lucide-vue-next';

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
}

interface Filiale {
    id: number;
    nom: string;
}

interface Props {
    agence: {
        id: number;
        nom: string;
        code_agent: string;
        description?: string;
        actif: boolean;
        chef_agence_id?: number;
        filiale_id?: number | null;
    };
    profils: Profil[];
    filiales?: Filiale[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agences',
        href: '/agences',
    },
    {
        title: 'Modifier l\'agence',
        href: '#',
    },
];

const form = useForm({
    nom: props.agence.nom,
    code_agent: props.agence.code_agent,
    description: props.agence.description || '',
    actif: props.agence.actif ? 'actif' : 'inactif' as 'actif' | 'inactif',
    chef_agence_id: props.agence.chef_agence_id || null,
    filiale_id: props.agence.filiale_id || null,
});

const submit = () => {
    form.put(`/agences/${props.agence.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier l'agence" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier l'agence</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="nom" class="text-base font-medium text-gray-700">Nom de l'agence *</Label>
                        <Input
                            id="nom"
                            v-model="form.nom"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div>
                        <Label for="code_agent" class="text-base font-medium text-gray-700">Code Agent *</Label>
                        <Input
                            id="code_agent"
                            v-model="form.code_agent"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.code_agent" />
                    </div>

                    <div class="col-span-2">
                        <Label for="description" class="text-base font-medium text-gray-700">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-1.5 flex w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div>
                        <Label for="actif" class="text-base font-medium text-gray-700">Statut</Label>
                        <select
                            id="actif"
                            v-model="form.actif"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                        <InputError :message="form.errors.actif" />
                    </div>

                    <div>
                        <Label for="filiale_id" class="text-base font-medium text-gray-700">Filiale</Label>
                        <select
                            id="filiale_id"
                            v-model="form.filiale_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option :value="null">Aucune filiale</option>
                            <option
                                v-for="filiale in props.filiales || []"
                                :key="filiale.id"
                                :value="filiale.id"
                            >
                                {{ filiale.nom }}
                            </option>
                        </select>
                        <InputError :message="form.errors.filiale_id" />
                    </div>

                    <div class="col-span-2">
                        <Label for="chef_agence_id" class="text-base font-medium text-gray-700">Chef d'agence</Label>
                        <select
                            id="chef_agence_id"
                            v-model="form.chef_agence_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option :value="null">Aucun</option>
                            <option
                                v-for="profil in props.profils"
                                :key="profil.id"
                                :value="profil.id"
                            >
                                {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                            </option>
                        </select>
                        <InputError :message="form.errors.chef_agence_id" />
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/agences')">
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

