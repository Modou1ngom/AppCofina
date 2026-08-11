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
import { computed, watch, withDefaults } from 'vue';
import { SIG_TYPES_RELATION, sigTypeRelationSelectClass } from '@/lib/sigTypeRelation';

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
    attachStaff?: {
        id: number;
        reference: string;
        prenom: string;
        nom: string;
    } | null;
}

const props = withDefaults(defineProps<Props>(), {
    attachStaff: null,
});

const page = usePage();
const pageErrors = computed(() => (page.props.errors || {}) as Record<string, string>);

const breadcrumbs: BreadcrumbItem[] = props.attachStaff
    ? [
          { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
          {
              title: props.attachStaff.reference,
              href: `/suivi-signature/staff/${props.attachStaff.id}`,
          },
          { title: 'Lier une personne', href: '#' },
      ]
    : [
          { title: 'Personnes liées', href: '/suivi-signature/personnes-liees' },
          { title: 'Recherche SI', href: '#' },
      ];

const cancelListHref = props.attachStaff
    ? `/suivi-signature/staff/${props.attachStaff.id}/lier-personnes`
    : '/suivi-signature/personnes-liees';

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
    attach_staff_id: props.attachStaff?.id ?? ('' as number | ''),
    type_relation: '',
    classe: 1 as number,
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
        let prenom = (d.prenom ?? '').trim();
        let nom = (d.nom ?? '').trim();
        if ((!prenom || !nom) && d.prenom_nom) {
            const parts = d.prenom_nom.trim().split(/\s+/);
            if (!prenom && !nom && parts.length >= 2) {
                nom = parts[0] ?? '';
                prenom = parts.slice(1).join(' ');
            } else if (!prenom && parts.length) {
                prenom = parts[0] ?? '';
            } else if (!nom && parts.length > 1) {
                nom = parts.slice(1).join(' ');
            }
        }
        storeForm.prenom = prenom || d.prenom_nom || d.matricule;
        storeForm.nom = nom || prenom || d.prenom_nom || d.matricule;
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
            attach_staff_id: props.attachStaff?.id ?? (data.attach_staff_id || null),
            encours_credit: data.encours_credit === '' ? null : data.encours_credit,
        }))
        .post('/suivi-signature/personnes-liees', { preserveScroll: true });
};

const nouvelleRecherche = () => {
    const q = props.attachStaff ? { reset: 1, staff_id: props.attachStaff.id } : { reset: 1 };
    router.get('/suivi-signature/personnes-liees/create', q);
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

            <div
                v-if="attachStaff"
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
            >
                Cette fiche sera liée uniquement au signataire
                <strong>{{ attachStaff.prenom }} {{ attachStaff.nom }}</strong>
                (réf. {{ attachStaff.reference }}).
            </div>

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
                    <div
                        v-if="Object.keys(storeForm.errors).length"
                        class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                    >
                        <p class="font-semibold">Impossible d’enregistrer — corrigez les points suivants :</p>
                        <ul class="mt-2 list-inside list-disc">
                            <li v-for="(msg, key) in storeForm.errors" :key="key">{{ msg }}</li>
                        </ul>
                    </div>

                    <Card v-if="attachStaff">
                        <CardHeader>
                            <CardTitle class="text-base">Relation avec le signataire</CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label for="type_relation">Type de relation *</Label>
                                <select
                                    id="type_relation"
                                    v-model="storeForm.type_relation"
                                    required
                                    :class="sigTypeRelationSelectClass"
                                >
                                    <option value="">— Choisir —</option>
                                    <option v-for="t in SIG_TYPES_RELATION" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                            <div>
                                <Label for="classe">Classe (1–4) *</Label>
                                <select
                                    id="classe"
                                    v-model.number="storeForm.classe"
                                    class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                                >
                                    <option :value="1">1</option>
                                    <option :value="2">2</option>
                                    <option :value="3">3</option>
                                    <option :value="4">4</option>
                                </select>
                            </div>
                        </CardContent>
                    </Card>

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
                        <Button type="submit" :disabled="storeForm.processing">
                            {{
                                attachStaff
                                    ? 'Enregistrer et lier à ce signataire'
                                    : 'Enregistrer dans le suivi signature'
                            }}
                        </Button>
                        <Button type="button" variant="outline" @click="router.visit(cancelListHref)">Annuler</Button>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
