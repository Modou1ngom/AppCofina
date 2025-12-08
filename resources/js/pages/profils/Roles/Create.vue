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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Rôles',
        href: '/roles',
    },
    {
        title: 'Créer un rôle',
        href: '#',
    },
];

const form = useForm({
    nom: '',
    description: '',
    actif: true,
});

const submit = () => {
    form.post('/roles', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Créer un rôle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Créer un rôle</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="nom" class="text-base font-medium text-gray-700">Nom du rôle *</Label>
                        <Input
                            id="nom"
                            v-model="form.nom"
                            type="text"
                            required
                            placeholder="Ex: Administrateur"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div class="col-span-2">
                        <Label for="description" class="text-base font-medium text-gray-700">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-1.5 flex w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                            placeholder="Description du rôle..."
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="col-span-2 flex items-center gap-2">
                        <Checkbox
                            id="actif"
                            :checked="form.actif"
                            @update:checked="(checked) => form.actif = checked"
                        />
                        <Label for="actif" class="text-sm font-normal text-gray-700 cursor-pointer">Rôle actif</Label>
                    </div>
                    <InputError :message="form.errors.actif" />
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/roles')">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer le rôle' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

