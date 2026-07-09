<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed } from 'vue';

interface Props {
    integrationExterne?: boolean;
    demande: {
        id: number;
        nom: string;
        prenom: string;
        matricule: string;
        montant: number;
        date_de_valeur: string | null;
        compte_staff: string | null;
        code_agence: string | null;
        compte_charge: string | null;
        libelle_ecriture: string | null;
        code_operation: string | null;
        ligne_credite_sens: string;
        ligne_debute_sens: string;
        ligne_credite_montant: number;
        ligne_debute_montant: number;
        ligne_debute_code_agence: string | null;
    };
}

const props = withDefaults(defineProps<Props>(), {
    integrationExterne: false,
});
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: 'Intégration', href: '/avances-salaire/integration-rh' },
    { title: `Formulaire #${props.demande.id}`, href: '#' },
];

const form = useForm({
    date_de_valeur: props.demande.date_de_valeur ?? '',
    code_operation: props.demande.code_operation ?? '',
    libelle_ecriture: props.demande.libelle_ecriture ?? '',
    ligne_credite: {
        no_compte: props.demande.compte_staff ?? '',
        sens: props.demande.ligne_credite_sens ?? 'credite',
        montant: props.demande.ligne_credite_montant ?? props.demande.montant,
        code_agence: props.demande.code_agence ?? '',
    },
    ligne_debute: {
        no_compte: props.demande.compte_charge ?? '',
        sens: props.demande.ligne_debute_sens ?? 'debute',
        montant: props.demande.ligne_debute_montant ?? props.demande.montant,
        code_agence: props.demande.ligne_debute_code_agence ?? props.demande.code_agence ?? '',
    },
});

const submit = () => {
    form.post(`/avances-salaire/${props.demande.id}/integration-rh`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Formulaire intégration #${demande.id}`" />

        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <div class="rounded-xl border bg-gradient-to-r from-red-50 via-white to-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold tracking-tight text-red-700">Formulaire d’intégration RH</h1>
                <p class="text-muted-foreground mt-1 text-sm leading-relaxed">
                    <template v-if="props.integrationExterne">
                        Contrôlez les informations puis validez : elles seront envoyées à l’application d’intégration (paie / opérations) configurée côté serveur.
                    </template>
                    <template v-else> Renseignez / ajustez les informations avant l’enregistrement des écritures dans AppCofina. </template>
                </p>
            </div>
            <div v-if="flash?.success" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                {{ flash.error }}
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Collaborateur</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">{{ demande.prenom }} {{ demande.nom }}</p>
                    <p class="text-xs text-muted-foreground">Matricule : {{ demande.matricule }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Montant</p>
                    <p class="mt-1 text-xl font-semibold text-red-700">{{ Number(demande.montant).toLocaleString('fr-FR') }} FCFA</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Dossier</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">#{{ demande.id }}</p>
                    <p class="text-xs text-muted-foreground">Prêt pour intégration</p>
                </div>
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-base font-semibold text-neutral-900">Informations générales</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <Label class="text-xs uppercase tracking-wide text-muted-foreground">Date de valeur</Label>
                    <Input v-model="form.date_de_valeur" type="date" class="mt-1" />
                    <InputError :message="form.errors.date_de_valeur" />
                </div>
                <div>
                    <Label class="text-xs uppercase tracking-wide text-muted-foreground">Code opération</Label>
                    <Input v-model="form.code_operation" class="mt-1" />
                    <InputError :message="form.errors.code_operation" />
                </div>
                <div>
                    <Label class="text-xs uppercase tracking-wide text-muted-foreground">Libellé écriture</Label>
                    <Input v-model="form.libelle_ecriture" class="mt-1" />
                    <InputError :message="form.errors.libelle_ecriture" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-base font-semibold text-neutral-900">Ligne créditée</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">No compte</Label>
                        <Input v-model="form.ligne_credite.no_compte" class="mt-1" />
                        <InputError :message="form.errors['ligne_credite.no_compte']" />
                    </div>
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Sens</Label>
                        <Input v-model="form.ligne_credite.sens" class="mt-1" />
                        <InputError :message="form.errors['ligne_credite.sens']" />
                    </div>
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Montant</Label>
                        <Input v-model.number="form.ligne_credite.montant" type="number" min="0" step="0.01" class="mt-1" />
                        <InputError :message="form.errors['ligne_credite.montant']" />
                    </div>
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Code agence</Label>
                        <Input v-model="form.ligne_credite.code_agence" class="mt-1" />
                        <InputError :message="form.errors['ligne_credite.code_agence']" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-base font-semibold text-neutral-900">Ligne débitée</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">No compte</Label>
                        <Input v-model="form.ligne_debute.no_compte" class="mt-1" />
                        <InputError :message="form.errors['ligne_debute.no_compte']" />
                    </div>
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Sens</Label>
                        <Input v-model="form.ligne_debute.sens" class="mt-1" />
                        <InputError :message="form.errors['ligne_debute.sens']" />
                    </div>
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Montant</Label>
                        <Input v-model.number="form.ligne_debute.montant" type="number" min="0" step="0.01" class="mt-1" />
                        <InputError :message="form.errors['ligne_debute.montant']" />
                    </div>
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Code agence</Label>
                        <Input v-model="form.ligne_debute.code_agence" class="mt-1" />
                        <InputError :message="form.errors['ligne_debute.code_agence']" />
                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 flex gap-2 rounded-xl border bg-white/95 p-4 backdrop-blur supports-[backdrop-filter]:bg-white/80">
                <Button variant="outline" as-child>
                    <Link href="/avances-salaire/integration-rh">Retour</Link>
                </Button>
                <Button class="bg-red-700 px-6 hover:bg-red-800" :disabled="form.processing" @click="submit">
                    {{
                        form.processing
                            ? 'Envoi...'
                            : props.integrationExterne
                              ? 'Transmettre à l’application d’intégration'
                              : 'Démarrer l’intégration'
                    }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
