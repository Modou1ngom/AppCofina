<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
}

interface Props {
    profils: Profil[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agences',
        href: '/agences',
    },
    {
        title: 'Créer une agence',
        href: '#',
    },
];

const form = useForm({
    nom: '',
    code_agent: '',
    description: '',
    actif: 'actif' as 'actif' | 'inactif',
    chef_agence_id: null as number | null,
});

const submit = () => {
    form.post('/agences', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Créer une agence" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold">Créer une nouvelle agence</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <div class="flex flex-col gap-4">
                        <div>
                            <Label for="nom">Nom de l'agence *</Label>
                            <Input
                                id="nom"
                                v-model="form.nom"
                                type="text"
                                required
                                placeholder="Ex: Agence Point E, Agence Thiès, etc."
                                class="mt-1"
                            />
                            <InputError :message="form.errors.nom" />
                        </div>

                        <div>
                            <Label for="code_agent">Code Agent *</Label>
                            <Input
                                id="code_agent"
                                v-model="form.code_agent"
                                type="text"
                                required
                                placeholder="Ex: 500, 501, 502, etc."
                                class="mt-1"
                            />
                            <InputError :message="form.errors.code_agent" />
                        </div>

                        <div>
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                placeholder="Description de l'agence..."
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div>
                            <Label for="actif">Statut</Label>
                            <select
                                id="actif"
                                v-model="form.actif"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                            <InputError :message="form.errors.actif" />
                        </div>

                        <div>
                            <Label for="chef_agence_id">Chef d'agence</Label>
                            <select
                                id="chef_agence_id"
                                v-model="form.chef_agence_id"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
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
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/agences')">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer l\'agence' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

