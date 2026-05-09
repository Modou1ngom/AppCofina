<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
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
    encours_total?: number | string | null;
    value_date?: string | null;
}

interface Props {
    siData: SiData | null;
    lookupDone: boolean;
}

const props = defineProps<Props>();

const page = usePage();
const pageErrors = computed(() => (page.props.errors || {}) as Record<string, string>);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Personnes liées', href: '/suivi-signature/personnes-liees' },
    { title: 'Recherche SI', href: '#' },
];

const cancelListHref = '/suivi-signature/personnes-liees';

const lookupForm = useForm({
    context: 'personne_liee' as const,
    matricule: '',
    type_client: 'personnel' as 'personnel' | 'entreprise',
});

const submitLookup = () => {
    lookupForm.post('/suivi-signature/lookup-client', { preserveScroll: true });
};

const storeForm = useForm({
    si_confirmed: true,
    numero_client: '',
    est_personne_morale: false,
    prenom: '',
    nom: '',
    raison_sociale: '',
    kyc_piece_identite: '',
    kyc_adresse: '',
    kyc_telephone: '',
    encours_credit: '' as string | number,
});

function applySiData(d: SiData) {
    storeForm.numero_client = d.matricule;
    storeForm.est_personne_morale = d.type_client === 'entreprise';
    if (d.type_client === 'personnel') {
        storeForm.prenom = d.prenom ?? '';
        storeForm.nom = d.nom ?? '';
        storeForm.raison_sociale = '';
    } else {
        storeForm.prenom = '';
        storeForm.nom = '';
        storeForm.raison_sociale = d.raison_sociale ?? d.prenom_nom;
    }
    storeForm.kyc_piece_identite = d.piece_numero ? `${d.piece_type} — ${d.piece_numero}` : d.piece_type;
    storeForm.kyc_adresse = d.adresse ?? '';
    storeForm.kyc_telephone = d.telephone ?? '';

    const enc = d.encours_total;
    if (enc != null && enc !== '') {
        const n = typeof enc === 'number' ? enc : parseFloat(String(enc).replace(',', '.'));
        if (!Number.isNaN(n)) {
            storeForm.encours_credit = Math.round(n * 100) / 100;
        }
    } else {
        storeForm.encours_credit = '';
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
            encours_credit: data.encours_credit === '' ? null : data.encours_credit,
        }))
        .post('/suivi-signature/personnes-liees', { preserveScroll: true });
};

const nouvelleRecherche = () => {
    router.get('/suivi-signature/personnes-liees/create');
};
</script>

<template>
    <Head title="Enregistrer une personne liée — SI" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-3xl flex-col gap-8 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Rechercher un client</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>
            <p class="text-muted-foreground -mt-4 text-sm">
                Même principe que le back office : le numéro client permet de récupérer la fiche dans le SI avant
                enregistrement dans le module personnes liées.
            </p>

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
                            <Input id="matricule" v-model="lookupForm.matricule" type="text" class="mt-1.5" placeholder="Numéro client" />
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
                    <Button type="button" class="bg-red-600 hover:bg-red-700" :disabled="lookupForm.processing" @click="submitLookup">
                        Rechercher
                    </Button>
                </CardContent>
            </Card>

            <template v-else-if="siData">
                <Button variant="outline" size="sm" class="w-fit" @click="nouvelleRecherche">← Nouvelle recherche</Button>

                <Card class="shadow-sm">
                    <CardHeader>
                        <CardTitle>Données du client</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="divide-y rounded-md border bg-white">
                            <div class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Prénom et nom / Raison sociale</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.prenom_nom }}</span>
                            </div>
                            <div v-if="siData.adresse" class="grid grid-cols-1 gap-1 px-4 py-3 md:grid-cols-[200px_1fr]">
                                <span class="text-sm font-semibold text-slate-600">Adresse</span>
                                <span class="text-sm font-semibold text-gray-900">{{ siData.adresse }}</span>
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

                <form class="space-y-6" @submit.prevent="submitStore">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Compléments — encours</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="max-w-sm">
                                <Label for="encours_credit">Encours crédit</Label>
                                <Input
                                    id="encours_credit"
                                    v-model="storeForm.encours_credit"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="mt-1.5 cursor-not-allowed bg-muted/60"
                                    readonly
                                />
                                <InputError :message="storeForm.errors.encours_credit" />
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="storeForm.processing">Enregistrer dans le suivi signature</Button>
                        <Button type="button" variant="outline" @click="router.visit(cancelListHref)">Annuler</Button>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
