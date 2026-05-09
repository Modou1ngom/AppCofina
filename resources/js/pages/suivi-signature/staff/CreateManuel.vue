<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Code, UserPlus } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
    { title: 'Membre CA (hors SI)', href: '#' },
];

const form = useForm({
    reference: '',
    prenom: '',
    nom: '',
    fonction: '',
    departement: '',
    statut: 'actif' as 'actif' | 'inactif',
    kyc_piece_identite: '',
    kyc_adresse: '',
    kyc_telephone: '',
    encours_credit_individuel: '' as string | number,
    score_risque: '' as string | number,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        encours_credit_individuel: data.encours_credit_individuel === '' ? null : data.encours_credit_individuel,
        score_risque: data.score_risque === '' ? null : data.score_risque,
    })).post('/suivi-signature/staff/manuel', { preserveScroll: true });
};
</script>

<template>
    <Head title="Membre CA — saisie conformité" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <UserPlus class="h-8 w-8 text-gray-700" />
                <h1 class="text-3xl font-bold text-gray-900">Membre du Conseil d’administration</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <Card class="border-amber-200 bg-amber-50/80 dark:bg-amber-950/20">
                <CardContent class="pt-6 text-sm text-amber-950 dark:text-amber-100">
                    Ces personnes ne sont en général <strong>pas</strong> présentes dans le SI : la <strong>conformité</strong> saisit
                    l’identifiant et les informations ici. Le type enregistré sera <strong>Administrateur (CA)</strong>, sans lien avec un
                    profil RH.
                </CardContent>
            </Card>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <FormSection title="Identification" :columns="2">
                    <div class="md:col-span-2">
                        <Label for="reference">Référence unique *</Label>
                        <Input
                            id="reference"
                            v-model="form.reference"
                            type="text"
                            required
                            class="mt-1.5 font-mono"
                            placeholder="Ex. CA-2025-001 (référence attribuée par la conformité)"
                        />
                        <InputError :message="form.errors.reference" />
                    </div>
                    <div>
                        <Label for="prenom">Prénom *</Label>
                        <Input id="prenom" v-model="form.prenom" required class="mt-1.5" />
                        <InputError :message="form.errors.prenom" />
                    </div>
                    <div>
                        <Label for="nom">Nom *</Label>
                        <Input id="nom" v-model="form.nom" required class="mt-1.5" />
                        <InputError :message="form.errors.nom" />
                    </div>
                    <div>
                        <Label for="fonction">Fonction</Label>
                        <Input id="fonction" v-model="form.fonction" class="mt-1.5" placeholder="Ex. Administrateur, membre élu…" />
                    </div>
                    <div>
                        <Label for="departement">Département / commission</Label>
                        <Input id="departement" v-model="form.departement" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="statut">Statut *</Label>
                        <select
                            id="statut"
                            v-model="form.statut"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </FormSection>

                <FormSection title="KYC &amp; encours" :columns="2">
                    <div>
                        <Label for="kyc_piece_identite">CNI / Passeport</Label>
                        <Input id="kyc_piece_identite" v-model="form.kyc_piece_identite" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="kyc_telephone">Téléphone</Label>
                        <Input id="kyc_telephone" v-model="form.kyc_telephone" class="mt-1.5" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="kyc_adresse">Adresse</Label>
                        <textarea
                            id="kyc_adresse"
                            v-model="form.kyc_adresse"
                            rows="2"
                            class="border-input bg-background mt-1.5 w-full rounded-md border px-3 py-2 text-sm shadow-sm"
                        />
                    </div>
                    <div>
                        <Label for="encours_credit_individuel">Encours crédit individuel</Label>
                        <Input id="encours_credit_individuel" v-model="form.encours_credit_individuel" type="number" step="0.01" min="0" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="score_risque">Score de risque (optionnel)</Label>
                        <Input id="score_risque" v-model="form.score_risque" type="number" step="0.01" min="0" class="mt-1.5" />
                    </div>
                </FormSection>

                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="form.processing">Enregistrer le membre CA</Button>
                    <Button type="button" variant="outline" @click="router.visit('/suivi-signature/staff')">Annuler</Button>
                    <Button type="button" variant="ghost" @click="router.visit('/suivi-signature/staff/create')">Recherche SI (staff)</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
