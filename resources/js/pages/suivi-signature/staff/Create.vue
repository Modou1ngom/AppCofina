<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Code, Search } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface SiData {
    matricule: string;
    type_client: 'personnel' | 'entreprise';
    profile_id?: number | null;
    prenom: string | null;
    nom: string | null;
    raison_sociale?: string | null;
    prenom_nom: string;
    adresse: string | null;
    genre: string | null;
    telephone: string | null;
    email: string | null;
    piece_type: string;
    piece_numero: string | null;
    agence: string | null;
    fonction: string | null;
    departement: string | null;
    /** Renseigné par Oracle (proxy Python, requête encours) */
    encours_total?: number | string | null;
    value_date?: string | null;
}

interface PersonneLieeSi {
    numero_client: string;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    est_personne_morale: boolean;
    type_relation: string;
    classe: number | null;
}

interface Props {
    siData: SiData | null;
    lookupDone: boolean;
    personnesLieesSi?: PersonneLieeSi[];
}

const props = withDefaults(defineProps<Props>(), {
    personnesLieesSi: () => [],
});

const page = usePage();
const pageErrors = computed(() => (page.props.errors || {}) as Record<string, string>);
const auth = computed(() => page.props.auth as { isAdmin?: boolean; isConformite?: boolean });
const canSaisieMembreCa = computed(() => !!(auth.value?.isAdmin || auth.value?.isConformite));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
    { title: 'Recherche SI', href: '#' },
];

const lookupForm = useForm({
    context: 'staff' as const,
    matricule: '',
    type_client: 'personnel' as 'personnel' | 'entreprise',
});

const submitLookup = () => {
    lookupForm.post('/suivi-signature/lookup-client', { preserveScroll: true });
};

const storeForm = useForm({
    si_confirmed: true,
    reference: '',
    profile_id: '' as number | '',
    prenom: '',
    nom: '',
    fonction: '',
    departement: '',
    type_personne: 'staff' as 'staff' | 'administrateur' | 'apparente_ou_liee',
    statut: 'actif' as 'actif' | 'inactif',
    kyc_piece_identite: '',
    kyc_adresse: '',
    kyc_telephone: '',
    encours_credit_individuel: '' as string | number,
    score_risque: '' as string | number,
});

function applySiData(d: SiData) {
    storeForm.reference = d.matricule;
    storeForm.profile_id = d.profile_id ?? '';
    if (d.type_client === 'personnel') {
        storeForm.prenom = d.prenom ?? '';
        storeForm.nom = d.nom ?? '';
    } else {
        storeForm.prenom = '—';
        storeForm.nom = d.raison_sociale ?? d.prenom_nom;
    }
    storeForm.fonction = d.fonction ?? '';
    storeForm.departement = d.departement ?? '';
    storeForm.kyc_piece_identite = d.piece_numero ? `${d.piece_type} — ${d.piece_numero}` : d.piece_type;
    storeForm.kyc_adresse = d.adresse ?? '';
    storeForm.kyc_telephone = d.telephone ?? '';

    const enc = d.encours_total;
    if (enc != null && enc !== '') {
        const n = typeof enc === 'number' ? enc : parseFloat(String(enc).replace(',', '.'));
        if (!Number.isNaN(n)) {
            storeForm.encours_credit_individuel = Math.round(n * 100) / 100;
        }
    } else {
        storeForm.encours_credit_individuel = '';
    }
}

watch(
    () => props.siData,
    (d) => {
        if (d) applySiData(d);
    },
    { immediate: true },
);

const submitStore = () => {
    storeForm
        .transform((data) => ({
            ...data,
            si_confirmed: true,
            profile_id: data.profile_id === '' ? null : data.profile_id,
            encours_credit_individuel: data.encours_credit_individuel === '' ? null : data.encours_credit_individuel,
            score_risque: data.score_risque === '' ? null : data.score_risque,
        }))
        .post('/suivi-signature/staff', { preserveScroll: true });
};

const nouvelleRecherche = () => {
    router.get('/suivi-signature/staff/create');
};
</script>

<template>
    <Head title="Enregistrer un staff — SI" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-3xl flex-col gap-8 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Rechercher un client</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>
            <p class="text-muted-foreground -mt-4 text-sm">
                Saisissez le numéro client (matricule) et le type : les données proviennent d’Oracle REPORT_GROUPE lorsque la
                recherche SQL est activée, sinon des profils / agences en base locale.
            </p>

            <div
                v-if="canSaisieMembreCa"
                class="rounded-lg border border-amber-200 bg-amber-50/90 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-50"
            >
                <strong>Membre du Conseil d’administration</strong> absent du SI ? La conformité peut l’enregistrer en saisie manuelle.
                <Link href="/suivi-signature/staff/manuel/create" class="text-primary ml-1 font-medium underline">Saisie membre CA (hors SI)</Link>
            </div>

            <!-- Étape 1 : recherche -->
            <Card v-if="!lookupDone" class="border-red-100 shadow-sm">
                <CardHeader class="border-b border-red-50 bg-gradient-to-r from-red-600/90 to-red-500/80 text-white">
                    <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                        <Search class="h-5 w-5" />
                        Rechercher un client
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 pt-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="matricule">Numéro client</Label>
                            <Input
                                id="matricule"
                                v-model="lookupForm.matricule"
                                type="text"
                                class="mt-1.5"
                                placeholder="Numéro client"
                                autocomplete="off"
                            />
                            <InputError :message="lookupForm.errors.matricule || pageErrors.matricule" />
                        </div>
                        <div>
                            <Label for="type_client">Type client</Label>
                            <select
                                id="type_client"
                                v-model="lookupForm.type_client"
                                class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                            >
                                <option value="personnel">Personnel</option>
                                <option value="entreprise">Entreprise</option>
                            </select>
                        </div>
                    </div>
                    <Button
                        type="button"
                        class="bg-red-600 hover:bg-red-700"
                        :disabled="lookupForm.processing"
                        @click="submitLookup"
                    >
                        Rechercher
                    </Button>
                </CardContent>
            </Card>

            <!-- Étape 2 : données SI + validation métier -->
            <template v-else-if="siData">
                <Button variant="outline" size="sm" class="w-fit" @click="nouvelleRecherche">← Nouvelle recherche</Button>

                <Card class="shadow-sm">
                    <CardHeader>
                        <CardTitle>Données du client</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="divide-y rounded-md border bg-white">
                            <div class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Prénom et nom</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.prenom_nom }}</span>
                            </div>
                            <div v-if="siData.adresse" class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Adresse</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.adresse }}</span>
                            </div>
                            <div v-if="siData.genre" class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Genre</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.genre }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Téléphone</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.telephone || '—' }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Email</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.email || '—' }}</span>
                            </div>
                            <div class="grid grid-cols-1 items-center gap-2 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Pièce d’identité</span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge variant="secondary" class="bg-emerald-600 text-white hover:bg-emerald-600">{{
                                        siData.piece_type
                                    }}</Badge>
                                    <span class="text-sm font-semibold text-gray-900">{{ siData.piece_numero || '—' }}</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Agence</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.agence || '—' }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="props.personnesLieesSi?.length" class="border-violet-100 shadow-sm">
                    <CardHeader>
                        <CardTitle>Personnes liées (Oracle REPORT_GROUPE)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground mb-4 text-sm">
                            Lecture seule à titre d’information. L’association à la fiche staff se fait après création depuis
                            les fiches « personnes liées » ou via votre processus métier.
                        </p>
                        <ul class="divide-y rounded-md border bg-white">
                            <li
                                v-for="(pl, idx) in props.personnesLieesSi"
                                :key="`${pl.numero_client}-${idx}`"
                                class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span class="text-sm font-semibold text-gray-900">
                                    <template v-if="pl.est_personne_morale && pl.raison_sociale">{{ pl.raison_sociale }}</template>
                                    <template v-else>{{ [pl.prenom, pl.nom].filter(Boolean).join(' ') || pl.numero_client }}</template>
                                    <span class="text-muted-foreground font-normal"> — {{ pl.numero_client }}</span>
                                </span>
                                <span class="text-sm text-slate-600">
                                    {{ pl.type_relation }}
                                    <template v-if="pl.classe != null"> · classe {{ pl.classe }}</template>
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <form class="space-y-6" @submit.prevent="submitStore">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Compléments — suivi signature</CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label for="type_personne">Type (réglementaire) *</Label>
                                <select
                                    id="type_personne"
                                    v-model="storeForm.type_personne"
                                    class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                                >
                                    <option value="staff">Staff</option>
                                    <option value="administrateur">Administrateur (CA)</option>
                                    <option value="apparente_ou_liee">Personne apparentée ou liée</option>
                                </select>
                                <InputError :message="storeForm.errors.type_personne" />
                            </div>
                            <div>
                                <Label for="statut">Statut *</Label>
                                <select
                                    id="statut"
                                    v-model="storeForm.statut"
                                    class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                                >
                                    <option value="actif">Actif</option>
                                    <option value="inactif">Inactif</option>
                                </select>
                            </div>
                            <div>
                                <Label for="encours_credit_individuel">Encours crédit individuel</Label>
                                <Input
                                    id="encours_credit_individuel"
                                    v-model="storeForm.encours_credit_individuel"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="mt-1.5 cursor-not-allowed bg-muted/60"
                                    readonly
                                />
                                <p v-if="props.siData?.value_date" class="text-muted-foreground mt-1 text-xs">
                                    Montant issu du SI (principal), valeur au {{ props.siData.value_date }} — lecture seule.
                                </p>
                                <p v-else-if="storeForm.encours_credit_individuel !== '' && storeForm.encours_credit_individuel != null" class="text-muted-foreground mt-1 text-xs">
                                    Montant issu du SI — lecture seule.
                                </p>
                            </div>
                            <div>
                                <Label for="score_risque">Score de risque (optionnel)</Label>
                                <Input id="score_risque" v-model="storeForm.score_risque" type="number" step="0.01" min="0" class="mt-1.5" />
                            </div>
                        </CardContent>
                    </Card>

                    <input type="hidden" name="si_confirmed" value="1" />
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="storeForm.processing">Enregistrer dans le suivi signature</Button>
                        <Button type="button" variant="outline" @click="router.visit('/suivi-signature/staff')">Annuler</Button>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
