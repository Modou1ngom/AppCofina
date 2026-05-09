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

interface Personne {
    id: number;
    numero_client: string | null;
    est_personne_morale: boolean;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    kyc_piece_identite: string | null;
    kyc_adresse: string | null;
    kyc_telephone: string | null;
    encours_credit: string;
}

interface Props {
    personneLiee: Personne;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Personnes liées', href: '/suivi-signature/personnes-liees' },
    { title: 'Modifier', href: '#' },
];

const form = useForm({
    est_personne_morale: props.personneLiee.est_personne_morale,
    prenom: props.personneLiee.prenom || '',
    nom: props.personneLiee.nom || '',
    raison_sociale: props.personneLiee.raison_sociale || '',
    kyc_piece_identite: props.personneLiee.kyc_piece_identite || '',
    kyc_adresse: props.personneLiee.kyc_adresse || '',
    kyc_telephone: props.personneLiee.kyc_telephone || '',
    encours_credit: props.personneLiee.encours_credit ?? '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        encours_credit: data.encours_credit === '' ? null : data.encours_credit,
    })).put(`/suivi-signature/personnes-liees/${props.personneLiee.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Modifier personne liée" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier la fiche</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div v-if="personneLiee.numero_client" class="rounded-lg border border-sidebar-border bg-muted/30 p-4 text-sm">
                    <span class="text-muted-foreground">N° client (SI) :</span>
                    <span class="ml-2 font-mono font-medium">{{ personneLiee.numero_client }}</span>
                </div>
                <FormSection title="Nature" :columns="1">
                    <div class="flex items-center gap-3">
                        <input id="morale" v-model="form.est_personne_morale" type="checkbox" class="size-4 rounded border" />
                        <Label for="morale" class="!mt-0 cursor-pointer font-normal">Personne morale</Label>
                    </div>
                </FormSection>

                <FormSection v-if="!form.est_personne_morale" title="Personne physique" :columns="2">
                    <div>
                        <Label for="prenom">Prénom *</Label>
                        <Input id="prenom" v-model="form.prenom" class="mt-1.5" />
                        <InputError :message="form.errors.prenom" />
                    </div>
                    <div>
                        <Label for="nom">Nom *</Label>
                        <Input id="nom" v-model="form.nom" class="mt-1.5" />
                        <InputError :message="form.errors.nom" />
                    </div>
                </FormSection>

                <FormSection v-else title="Personne morale" :columns="1">
                    <div>
                        <Label for="raison_sociale">Raison sociale *</Label>
                        <Input id="raison_sociale" v-model="form.raison_sociale" class="mt-1.5" />
                        <InputError :message="form.errors.raison_sociale" />
                    </div>
                </FormSection>

                <FormSection title="KYC &amp; encours" :columns="2">
                    <div>
                        <Label for="kyc_piece_identite">CNI / Passeport / RCCM</Label>
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
                        <Label for="encours_credit">Encours crédit</Label>
                        <Input id="encours_credit" v-model="form.encours_credit" type="number" step="0.01" min="0" class="mt-1.5" />
                    </div>
                </FormSection>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                    <Button type="button" variant="outline" @click="router.visit(`/suivi-signature/personnes-liees/${personneLiee.id}`)">
                        Annuler
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
