<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { ref } from 'vue';

interface Bareme {
    id: number;
    key: string;
    label: string;
    compte_charge: string | null;
    code_operation: string | null;
    duree_max_mois: number;
    plafond_non_cadre: number;
    plafond_cadre: number;
    plafond_emc: number;
    sort_order: number;
    is_active: boolean;
}

const props = defineProps<{ baremes: Bareme[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: 'Paramétrage', href: '#' },
];

const formCreate = useForm({
    key: '',
    label: '',
    compte_charge: '',
    code_operation: '',
    duree_max_mois: 3,
    plafond_non_cadre: 300000,
    plafond_cadre: 500000,
    plafond_emc: 1500000,
    sort_order: 0,
    is_active: true,
});

const edits = ref<Record<number, Bareme>>(
    Object.fromEntries(
        props.baremes.map((b) => [
            b.id,
            {
                ...b,
                compte_charge: b.compte_charge ?? '',
                code_operation: b.code_operation ?? '',
            },
        ]),
    ),
);

const createBareme = () => {
    formCreate.post('/avances-salaire/parametrage', { preserveScroll: true });
};

const saveBareme = (id: number) => {
    router.patch(`/avances-salaire/parametrage/${id}`, edits.value[id], { preserveScroll: true });
};

const deleteBareme = (id: number) => {
    if (!confirm('Supprimer ce type d’avance ?')) return;
    router.delete(`/avances-salaire/parametrage/${id}`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Paramétrage des avances" />

        <div class="space-y-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Paramétrage des barèmes</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Définissez dynamiquement les types d’avances, comptes de charge, durée max et plafonds.
                </p>
            </div>

            <div class="rounded-md border p-4">
                <h2 class="mb-4 text-lg font-semibold">Ajouter un type</h2>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div>
                        <Label>Clé technique</Label>
                        <Input v-model="formCreate.key" placeholder="ex: salaire" />
                    </div>
                    <div>
                        <Label>Libellé</Label>
                        <Input v-model="formCreate.label" placeholder="Avance sur salaire" />
                    </div>
                    <div>
                        <Label>Compte charge</Label>
                        <Input v-model="formCreate.compte_charge" />
                    </div>
                    <div>
                        <Label>Code opération</Label>
                        <Input v-model="formCreate.code_operation" placeholder="ex: 310" />
                    </div>
                    <div>
                        <Label>Durée max (mois)</Label>
                        <Input v-model.number="formCreate.duree_max_mois" type="number" min="1" />
                    </div>
                    <div>
                        <Label>Plafond non cadre</Label>
                        <Input v-model.number="formCreate.plafond_non_cadre" type="number" min="0" />
                    </div>
                    <div>
                        <Label>Plafond cadre</Label>
                        <Input v-model.number="formCreate.plafond_cadre" type="number" min="0" />
                    </div>
                    <div>
                        <Label>Plafond EMC</Label>
                        <Input v-model.number="formCreate.plafond_emc" type="number" min="0" />
                    </div>
                    <div>
                        <Label>Ordre</Label>
                        <Input v-model.number="formCreate.sort_order" type="number" min="0" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <Checkbox
                        :checked="formCreate.is_active"
                        @update:checked="(v: boolean | 'indeterminate') => (formCreate.is_active = v === true)"
                    />
                    <span class="text-sm">Actif</span>
                </div>
                <Button class="mt-4" @click="createBareme">Ajouter</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 border-b">
                        <tr>
                            <th class="p-2 text-left">Type</th>
                            <th class="p-2 text-left">Compte</th>
                            <th class="p-2 text-left">Code opération</th>
                            <th class="p-2 text-left">Durée max</th>
                            <th class="p-2 text-left">Plafond NC</th>
                            <th class="p-2 text-left">Plafond Cadre</th>
                            <th class="p-2 text-left">Plafond EMC</th>
                            <th class="p-2 text-left">Actif</th>
                            <th class="p-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in baremes" :key="b.id" class="border-b">
                            <td class="p-2">
                                <Input v-model="edits[b.id].label" />
                                <p class="text-muted-foreground mt-1 text-xs">{{ edits[b.id].key }}</p>
                            </td>
                            <td class="p-2"><Input v-model="edits[b.id].compte_charge" /></td>
                            <td class="p-2"><Input v-model="edits[b.id].code_operation" /></td>
                            <td class="p-2"><Input v-model.number="edits[b.id].duree_max_mois" type="number" min="1" /></td>
                            <td class="p-2"><Input v-model.number="edits[b.id].plafond_non_cadre" type="number" min="0" /></td>
                            <td class="p-2"><Input v-model.number="edits[b.id].plafond_cadre" type="number" min="0" /></td>
                            <td class="p-2"><Input v-model.number="edits[b.id].plafond_emc" type="number" min="0" /></td>
                            <td class="p-2">
                                <Checkbox
                                    :checked="edits[b.id].is_active"
                                    @update:checked="(v: boolean | 'indeterminate') => (edits[b.id].is_active = v === true)"
                                />
                            </td>
                            <td class="p-2 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" @click="saveBareme(b.id)">Enregistrer</Button>
                                    <Button size="sm" variant="destructive" @click="deleteBareme(b.id)">Supprimer</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
