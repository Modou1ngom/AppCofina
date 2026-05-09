<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed, watch } from 'vue';
import { AlertTriangle, Building2, CalendarDays, CreditCard, FileText, Info, Send, UserRound } from 'lucide-vue-next';

interface Profil {
    id: number;
    matricule: string;
    nom: string;
    prenom: string;
    type_contrat?: string;
    departement?: string;
    date_entree?: string | null;
    numero_compte?: string | null;
    statut_rh?: string | null;
    categorie_staff_suggeree?: string;
}

interface Defaults {
    plafond_pct: number;
    duree_mois_min: number;
    duree_mois_max: number;
    anciennete_mois_min: number;
    taux_interet_defaut: number;
}

interface BaremeAvance {
    key: string;
    label: string;
    compte_charge: string;
    duree_max_mois: number;
    plafonds: Record<string, number>;
    modes_remboursement?: string[];
    mode_remboursement_defaut?: string;
}

interface Props {
    profil: Profil;
    defaults: Defaults;
    baremes: BaremeAvance[];
}

type CategorieStaff = 'non_cadre' | 'cadre' | 'emc';

const props = defineProps<Props>();
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: 'Nouvelle demande', href: '#' },
];

const form = useForm({
    type_avance: 'salaire',
    mode_paiement: 'par_mois',
    dates_tranches: [] as string[],
    categorie_staff: (props.profil.categorie_staff_suggeree ?? 'non_cadre') as CategorieStaff,
    compte_staff: props.profil.numero_compte ? String(props.profil.numero_compte).trim() : '',
    nombre_avance_en_cours: 0,
    montant: '' as string | number,
    duree_mois: 3,
    date_premiere_echeance: '',
    taux_interet_annuel_pct: props.defaults.taux_interet_defaut,
});

const baremes = computed(() => props.baremes ?? []);
const baremeSelectionne = computed(() => baremes.value.find((b) => b.key === form.type_avance) ?? baremes.value[0] ?? null);
const modesPaiementDisponibles = computed(() => {
    const options = [...(baremeSelectionne.value?.modes_remboursement ?? ['par_mois'])];
    if (form.type_avance === 'salaire' && !options.includes('par_tranche')) {
        options.push('par_tranche');
    }
    return options.length ? options : ['par_mois'];
});

const libelleModePaiement = (mode: string) => (mode === 'par_tranche' ? 'Par tranche' : 'Par mois');
const isParTranche = computed(() => form.mode_paiement === 'par_tranche');
const dureeMin = computed(() => props.defaults.duree_mois_min);
const dureeMax = computed(() => baremeSelectionne.value?.duree_max_mois ?? props.defaults.duree_mois_max);
const plafondActuel = computed(() => {
    const bareme = baremeSelectionne.value;
    if (!bareme) return 0;
    return bareme.plafonds[form.categorie_staff] ?? bareme.plafonds.non_cadre ?? 0;
});
const today = computed(() => new Date().toLocaleDateString('fr-FR'));
const nomComplet = computed(() => `${props.profil.prenom} ${props.profil.nom}`.trim());
const profil = computed(() => props.profil);

function initDatesTranchesPourTranche() {
    form.dates_tranches = form.date_premiere_echeance ? [form.date_premiere_echeance] : [''];
}

function ajouterDateTranche() {
    form.dates_tranches.push('');
}

function retirerDateTranche(index: number) {
    if (form.dates_tranches.length <= 1) return;
    form.dates_tranches.splice(index, 1);
}

watch(
    () => form.type_avance,
    () => {
        const modeDefaut = baremeSelectionne.value?.mode_remboursement_defaut ?? modesPaiementDisponibles.value[0] ?? 'par_mois';
        form.mode_paiement = modesPaiementDisponibles.value.includes(modeDefaut) ? modeDefaut : (modesPaiementDisponibles.value[0] ?? 'par_mois');
        form.duree_mois = dureeMax.value;
        if (form.mode_paiement === 'par_tranche') {
            initDatesTranchesPourTranche();
        } else {
            form.dates_tranches = [];
        }
    },
    { immediate: true },
);

watch(
    () => form.mode_paiement,
    (m) => {
        if (m === 'par_tranche') {
            if (form.dates_tranches.length === 0) {
                initDatesTranchesPourTranche();
            }
        } else {
            form.dates_tranches = [];
        }
    },
);

watch(
    () => form.duree_mois,
    (value) => {
        if (value < dureeMin.value) {
            form.duree_mois = dureeMin.value;
            return;
        }
        if (value > dureeMax.value) {
            form.duree_mois = dureeMax.value;
        }
    },
);

const submit = () => {
    if (!confirm('Valider votre demande ? Elle sera transmise aux ressources humaines et ne pourra plus être modifiée.')) {
        return;
    }
    form.post('/avances-salaire', { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Nouvelle demande d'avance sur salaire" />

        <div class="min-h-screen bg-slate-50 p-6">
            <form class="mx-auto max-w-7xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="flex items-start gap-3 border-b border-slate-200 pb-5">
                    <div class="rounded-full bg-rose-50 p-2.5 text-rose-600">
                        <CreditCard class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-semibold text-slate-900">Demande d'avance sur salaire</h1>
                        <p class="mt-1 text-sm text-slate-500">Remplissez les informations nécessaires pour soumettre votre demande.</p>
                    </div>
                </div>
                <div v-if="flash?.error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ flash.error }}
                </div>
                <div v-if="(form.errors as Record<string, string>).eligibilite" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ (form.errors as Record<string, string>).eligibilite }}
                </div>

                <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-4">
                    <div class="flex items-start gap-3 rounded-lg bg-white p-3">
                        <CalendarDays class="mt-0.5 h-4 w-4 text-blue-600" />
                        <div>
                            <p class="text-xs text-slate-500">Date de la demande</p>
                            <p class="font-medium text-slate-900">{{ today }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-white p-3">
                        <UserRound class="mt-0.5 h-4 w-4 text-blue-600" />
                        <div>
                            <p class="text-xs text-slate-500">Demandeur</p>
                            <p class="font-medium text-slate-900">{{ nomComplet }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-white p-3">
                        <FileText class="mt-0.5 h-4 w-4 text-blue-600" />
                        <div>
                            <p class="text-xs text-slate-500">Type de contrat</p>
                            <p class="font-medium text-slate-900">{{ profil.type_contrat || 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-white p-3">
                        <Building2 class="mt-0.5 h-4 w-4 text-blue-600" />
                        <div>
                            <p class="text-xs text-slate-500">Département</p>
                            <p class="font-medium text-slate-900">{{ profil.departement || 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
                        <FileText class="h-4 w-4 text-rose-600" />
                        <h2 class="font-semibold text-slate-900">Informations de la demande</h2>
                    </div>

                    <div class="grid gap-4 px-4 pb-4 md:grid-cols-4">
                        <div>
                            <Label class="mb-2 block">Type d'avance</Label>
                            <select v-model="form.type_avance" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
                                <option v-for="b in baremes" :key="b.key" :value="b.key">{{ b.label }}</option>
                            </select>
                            <InputError :message="form.errors.type_avance" />
                        </div>
                        <div>
                            <Label class="mb-2 block">Montant de l'avance (FCFA)</Label>
                            <Input v-model="form.montant" type="number" class="h-11" />
                            <InputError :message="form.errors.montant" />
                        </div>
                        <div>
                            <Label class="mb-2 block">Mode de paiement</Label>
                            <select v-model="form.mode_paiement" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
                                <option v-for="mode in modesPaiementDisponibles" :key="mode" :value="mode">{{ libelleModePaiement(mode) }}</option>
                            </select>
                            <InputError :message="form.errors.mode_paiement" />
                        </div>
                        <div>
                            <Label class="mb-2 block">Durée de remboursement (mois)</Label>
                            <Input v-model.number="form.duree_mois" type="number" class="h-11" />
                            <p class="mt-1 text-xs text-slate-500">Intervalle autorisé : {{ dureeMin }} à {{ dureeMax }} mois.</p>
                            <InputError :message="form.errors.duree_mois" />
                        </div>

                        <div>
                            <Label class="mb-2 block">N° de compte staff</Label>
                            <Input v-model="form.compte_staff" class="h-11" />
                            <InputError :message="form.errors.compte_staff" />
                        </div>
                        <div>
                            <Label class="mb-2 block">Nombre d'avance en cours</Label>
                            <Input v-model.number="form.nombre_avance_en_cours" type="number" min="0" class="h-11" />
                            <InputError :message="form.errors.nombre_avance_en_cours" />
                        </div>
                        <div>
                            <Label class="mb-2 block">Plafond applicable (FCFA)</Label>
                            <Input :model-value="plafondActuel.toLocaleString('fr-FR')" disabled class="h-11 bg-slate-100" />
                        </div>
                        <div>
                            <Label class="mb-2 block">Date première échéance</Label>
                            <Input v-model="form.date_premiere_echeance" type="date" class="h-11" />
                            <InputError :message="form.errors.date_premiere_echeance" />
                        </div>
                    </div>

                    <div v-if="isParTranche" class="mx-4 mb-4 space-y-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <div class="flex items-start gap-2">
                            <Info class="mt-0.5 h-4 w-4 text-blue-600" />
                            <div>
                                <Label class="block text-slate-900">Dates des tranches</Label>
                                <p class="text-xs text-slate-600">Indiquez chaque date à laquelle vous souhaitez un prélèvement.</p>
                            </div>
                        </div>

                        <div class="grid gap-2 md:grid-cols-4">
                            <div v-for="(_d, i) in form.dates_tranches" :key="i" class="flex items-center gap-2">
                                <Input v-model="form.dates_tranches[i]" type="date" class="h-10" />
                                <Button type="button" variant="outline" size="icon" @click="retirerDateTranche(i)">x</Button>
                            </div>
                            <Button type="button" variant="outline" class="h-10 border-dashed text-blue-700" @click="ajouterDateTranche">
                                + Ajouter une date
                            </Button>
                        </div>
                        <InputError :message="form.errors.dates_tranches" />
                    </div>
                </div>


                <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                    <Button variant="outline" as-child class="px-6">
                        <Link href="/avances-salaire">Retour</Link>
                    </Button>
                    <Button type="submit" class="bg-rose-600 px-6 text-white hover:bg-rose-700">
                        <Send class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Envoi...' : 'Valider et transmettre aux RH' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>