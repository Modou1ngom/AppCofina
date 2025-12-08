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

interface Application {
    id: number;
    nom: string;
    description?: string;
    actif: boolean;
    ordre: number;
}

interface Props {
    application: Application;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Applications',
        href: '/applications',
    },
    {
        title: 'Modifier l\'application',
        href: '#',
    },
];

const form = useForm({
    nom: props.application.nom,
    description: props.application.description || '',
    actif: props.application.actif,
    ordre: props.application.ordre,
});

const submit = () => {
    form.put(`/applications/${props.application.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier l'application" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier l'application</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="nom" class="text-base font-medium text-gray-700">Nom de l'application *</Label>
                        <Input
                            id="nom"
                            v-model="form.nom"
                            type="text"
                            required
                            placeholder="Ex: Outlook"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div>
                        <Label for="actif" class="text-base font-medium text-gray-700">Statut</Label>
                        <select
                            id="actif"
                            v-model="form.actif"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option :value="true">Actif</option>
                            <option :value="false">Inactif</option>
                        </select>
                        <InputError :message="form.errors.actif" />
                    </div>

                    <div>
                        <Label for="ordre" class="text-base font-medium text-gray-700">Ordre d'affichage</Label>
                        <Input
                            id="ordre"
                            v-model.number="form.ordre"
                            type="number"
                            min="0"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.ordre" />
                        <p class="mt-1 text-xs text-gray-500">Détermine l'ordre d'affichage dans les listes</p>
                    </div>

                    <div class="col-span-2">
                        <Label for="description" class="text-base font-medium text-gray-700">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-1.5 flex w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                            placeholder="Description de l'application..."
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/applications')">
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

