<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage' },
    { title: 'Mes déclarations', href: '/pointage/declarations' },
    { title: 'Nouvelle', href: '#' },
];

const form = useForm({
    date_concernee: new Date().toISOString().slice(0, 10),
    motif: '',
});

const submit = () => {
    form.post('/pointage/declarations', { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Nouvelle déclaration de pointage" />

        <div class="mx-auto flex max-w-2xl flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Nouvelle déclaration</h1>
                <p class="mt-1 text-sm text-muted-foreground">Décrivez la situation (obligatoire pour traitement par votre manager puis les RH).</p>
            </div>

            <form class="space-y-4 rounded-lg border bg-card p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <Label for="date_concernee">Date concernée</Label>
                    <Input id="date_concernee" v-model="form.date_concernee" type="date" class="mt-1" />
                    <p v-if="form.errors.date_concernee" class="mt-1 text-sm text-destructive">{{ form.errors.date_concernee }}</p>
                </div>
                <div>
                    <Label for="motif">Motif</Label>
                    <textarea
                        id="motif"
                        v-model="form.motif"
                        rows="5"
                        class="mt-1 flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        placeholder="Ex. oubli de badge, mission extérieure, etc."
                    />
                    <p v-if="form.errors.motif" class="mt-1 text-sm text-destructive">{{ form.errors.motif }}</p>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Envoyer</Button>
                    <Button type="button" variant="outline" as-child>
                        <Link href="/pointage/declarations">Annuler</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
