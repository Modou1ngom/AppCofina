<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';

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
            <h1 class="text-2xl font-bold">Créer un nouveau rôle</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <div class="flex flex-col gap-4">
                        <div>
                            <Label for="nom">Nom du rôle *</Label>
                            <Input
                                id="nom"
                                v-model="form.nom"
                                type="text"
                                required
                                placeholder="Ex: Administrateur, Utilisateur, etc."
                                class="mt-1"
                            />
                            <InputError :message="form.errors.nom" />
                        </div>

                        <div>
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                placeholder="Description du rôle..."
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="actif"
                                :checked="form.actif"
                                @update:checked="(checked) => form.actif = checked"
                            />
                            <Label for="actif" class="font-normal">Rôle actif</Label>
                        </div>
                        <InputError :message="form.errors.actif" />
                    </div>
                </div>

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

