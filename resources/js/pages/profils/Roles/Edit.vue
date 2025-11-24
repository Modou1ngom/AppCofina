<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';

interface Props {
    role: {
        id: number;
        nom: string;
        slug: string;
        description?: string;
        actif: boolean;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Rôles',
        href: '/roles',
    },
    {
        title: 'Modifier le rôle',
        href: '#',
    },
];

const form = useForm({
    nom: props.role.nom,
    description: props.role.description || '',
    actif: props.role.actif,
});

const submit = () => {
    form.put(`/roles/${props.role.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier le rôle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold">Modifier le rôle</h1>

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
                        {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

