<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage/rapport' },
    { title: 'Sites', href: '/pointage/sites' },
    { title: 'Créer', href: '#' },
];

const form = useForm({
    nom: '',
    description: '',
    actif: true,
});

const submit = () => {
    form.post('/pointage/sites', { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Nouveau site de pointage" />

        <div class="mx-auto flex max-w-xl flex-col gap-6 p-6">
            <h1 class="text-2xl font-semibold tracking-tight">Nouveau site</h1>

            <form class="space-y-4 rounded-lg border bg-card p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <Label for="nom">Nom du lieu *</Label>
                    <Input id="nom" v-model="form.nom" class="mt-1" />
                    <p v-if="form.errors.nom" class="mt-1 text-sm text-destructive">{{ form.errors.nom }}</p>
                </div>
                <div>
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.actif" type="checkbox" class="rounded border-input" />
                    Site actif
                </label>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                    <Button type="button" variant="outline" as-child>
                        <Link href="/pointage/sites">Annuler</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
