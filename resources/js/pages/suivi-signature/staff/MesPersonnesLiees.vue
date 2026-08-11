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
import { Code, Link2, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { SIG_TYPES_RELATION, sigTypeRelationSelectClass } from '@/lib/sigTypeRelation';

interface PersonnePivot {
    id: number;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    est_personne_morale: boolean;
    encours_credit: string;
    pivot: {
        type_relation: string;
        classe: number;
    };
}

interface Staff {
    id: number;
    reference: string;
    numero_client_si: string | null;
    prenom: string;
    nom: string;
    fonction: string | null;
    departement: string | null;
    type_personne: string;
    statut: string;
    kyc_piece_identite: string | null;
    kyc_adresse: string | null;
    kyc_telephone: string | null;
    encours_staff_si: string;
    encours_credit_individuel: string;
    fonds_propres: string | null;
    score_risque: string | null;
    personnes_liees: PersonnePivot[];
}

interface SigMetriquesEncours {
    encours_staff_si: number;
    encours_personnes_liees: number;
    encours_total: number;
    fonds_propres: number | null;
    taux_encours_pct: number | null;
    seuil_taux_pct: number;
    depasse_seuil_encours: boolean;
    liaison_bloquee_encours: boolean;
}

interface SiData {
    matricule: string;
    type_client: string;
    prenom_nom: string;
    adresse: string | null;
    telephone: string | null;
    email: string | null;
    piece_type: string;
    piece_numero: string | null;
    agence: string | null;
    /** Fusion requête encours (proxy Oracle / mapping Laravel) */
    encours_total?: number | string | null;
    value_date?: string | null;
}

interface PersonneResolue {
    id: number;
    numero_client: string | null;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    est_personne_morale: boolean;
    encours_credit: string;
}

interface Props {
    staff: Staff | null;
    sigMetriquesEncours?: SigMetriquesEncours | null;
    missingProfil: boolean;
    missingFiche: boolean;
    profilMatricule: string | null;
    requiresSynchronisationClientSi?: boolean;
}

const props = defineProps<Props>();

const initForm = useForm({
    matricule: '',
});

watch(
    () => props.profilMatricule,
    (m) => {
        if (props.missingFiche && m) {
            initForm.matricule = m;
        }
    },
    { immediate: true },
);

function submitInitFiche() {
    initForm.post('/suivi-signature/staff/ma-fiche/initialiser', {
        preserveScroll: true,
    });
}

const requiresClientSi = computed(() => Boolean(props.requiresSynchronisationClientSi));

const clientSiForm = useForm({
    matricule_client: '',
});

function submitClientSi() {
    clientSiForm.post('/suivi-signature/staff/ma-fiche/synchroniser-client-si', {
        preserveScroll: true,
        onSuccess: () => {
            clientSiForm.reset();
        },
    });
}

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mes personnes liées', href: '#' }];

const libellePersonne = (p: PersonnePivot | PersonneResolue) => {
    if (p.est_personne_morale && p.raison_sociale) return p.raison_sociale;
    return `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || `#${p.id}`;
};

/** Encours affiché dans l’aperçu SI : priorité au montant renvoyé par le SI, sinon fiche locale. */
function montantEncoursApercu(liaison: { siData: SiData; personneLiee: PersonneResolue }): number {
    const raw = liaison.siData.encours_total;
    if (raw != null && raw !== '') {
        const n = typeof raw === 'number' ? raw : parseFloat(String(raw).replace(',', '.'));
        if (!Number.isNaN(n)) {
            return n;
        }
    }
    return Number(liaison.personneLiee.encours_credit);
}

function formatMontantFr(n: number): string {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const metriques = computed(() => props.sigMetriquesEncours);
const liaisonBloquee = computed(() => Boolean(metriques.value?.liaison_bloquee_encours));

const idsLies = computed(() =>
    props.staff ? new Set(props.staff.personnes_liees.map((p) => p.id)) : new Set<number>(),
);

const attachForm = useForm({
    sig_personne_liee_id: '' as number | '',
    type_relation: '',
    classe: 1 as number,
});

const resolveForm = ref({
    matricule: '',
    type_client: 'personnel' as 'personnel' | 'entreprise',
});

const resolving = ref(false);
const resolveMessage = ref<string | null>(null);
const resolvedLiaison = ref<{ siData: SiData; personneLiee: PersonneResolue } | null>(null);

watch(
    () => [resolveForm.value.matricule, resolveForm.value.type_client],
    () => {
        resolveMessage.value = null;
        resolvedLiaison.value = null;
        attachForm.sig_personne_liee_id = '';
    },
);

function csrfTokenForRequest(): string {
    const fromProps = (page.props as { csrf_token?: string }).csrf_token;
    if (fromProps) {
        return fromProps;
    }
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function resolveParMatricule() {
    if (!props.staff) return;
    if (liaisonBloquee.value) {
        resolveMessage.value =
            'Nouvelles liaisons sont bloquées : le taux encours / fonds propres dépasse le seuil réglementaire.';
        return;
    }
    resolveMessage.value = null;
    resolvedLiaison.value = null;
    attachForm.sig_personne_liee_id = '';
    resolving.value = true;
    try {
        const csrfToken = csrfTokenForRequest();
        const res = await fetch('/suivi-signature/personne-liee/resolve-matricule', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                _token: csrfToken,
                matricule: resolveForm.value.matricule.trim(),
                type_client: resolveForm.value.type_client,
            }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            const errMsg =
                data.message ??
                (data.errors && (data.errors.matricule?.[0] || data.errors.type_client?.[0])) ??
                'Recherche impossible.';
            resolveMessage.value = errMsg;
            return;
        }
        resolvedLiaison.value = { siData: data.siData as SiData, personneLiee: data.personneLiee as PersonneResolue };
        attachForm.sig_personne_liee_id = data.personneLiee.id;
        if (idsLies.value.has(data.personneLiee.id)) {
            resolveMessage.value = 'Cette personne est déjà liée à votre fiche.';
            resolvedLiaison.value = null;
            attachForm.sig_personne_liee_id = '';
        }
    } catch {
        resolveMessage.value = 'Erreur réseau. Réessayez.';
    } finally {
        resolving.value = false;
    }
}

const attach = () => {
    if (!props.staff) return;
    attachForm.post(`/suivi-signature/staff/${props.staff.id}/personnes-liees`, {
        preserveScroll: true,
        onSuccess: () => {
            attachForm.reset();
            attachForm.classe = 1;
            resolveForm.value.matricule = '';
            resolvedLiaison.value = null;
            resolveMessage.value = null;
        },
    });
};

const detach = (personneLieeId: number) => {
    if (!props.staff) return;
    if (!confirm('Retirer ce lien ?')) return;
    router.delete(`/suivi-signature/staff/${props.staff.id}/personnes-liees/${personneLieeId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Mes personnes liées — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">Mes personnes apparentées ou liées</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"
            >
                {{ flash.error }}
            </div>

            <div
                v-if="metriques?.depasse_seuil_encours"
                class="rounded-lg border-2 border-red-600 bg-red-50 p-4 text-sm text-red-900 shadow-sm"
            >
                <p class="font-semibold">Alerte — seuil d’encours dépassé</p>
                <p class="mt-1">
                    Taux encours / fonds propres :
                    <strong>{{ metriques.taux_encours_pct?.toLocaleString('fr-FR') ?? '—' }} %</strong>
                    (seuil : {{ metriques.seuil_taux_pct.toLocaleString('fr-FR') }} %). Une notification a été enregistrée.
                </p>
                <p v-if="metriques.liaison_bloquee_encours" class="mt-2 font-medium">
                    Les nouvelles liaisons sont bloquées jusqu’à correction (réduire les encours, ajuster les fonds propres de référence ou
                    retirer des liens).
                </p>
            </div>

            <div
                v-if="missingProfil"
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
            >
                Aucun profil RH n’est associé à votre compte (email). Contactez l’administration pour lier votre compte à un profil
                : vous ne pouvez pas déclarer de personnes liées tant que cette liaison n’existe pas.
            </div>

            <Card v-else-if="missingFiche" class="border-amber-100 shadow-sm">
                <CardHeader class="border-b border-amber-50 bg-amber-50/80">
                    <CardTitle class="text-lg font-semibold text-amber-950">Activer ma fiche suivi signature</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 pt-6">
                    <p class="text-muted-foreground text-sm">
                        Saisissez votre <strong>matricule RH</strong> (celui de votre profil). Nous interrogeons le SI et créons votre
                        fiche. Ensuite, vous saisirez votre <strong>numéro client SI</strong> pour récupérer le KYC et votre encours propre ;
                        seulement après cela vous pourrez déclarer vos personnes apparentées ou liées.
                    </p>
                    <form class="max-w-md space-y-4" @submit.prevent="submitInitFiche">
                        <div>
                            <Label for="matricule_init">Matricule *</Label>
                            <Input
                                id="matricule_init"
                                v-model="initForm.matricule"
                                type="text"
                                class="mt-1.5"
                                required
                                autocomplete="username"
                            />
                            <InputError class="mt-1" :message="initForm.errors.matricule" />
                        </div>
                        <Button type="submit" class="bg-amber-700 hover:bg-amber-800" :disabled="initForm.processing">
                            {{ initForm.processing ? 'Vérification…' : 'Valider et créer ma fiche' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <template v-else-if="staff">
                <Card v-if="requiresClientSi" class="border-amber-200 shadow-sm">
                    <CardHeader class="border-b border-amber-100 bg-amber-50/90">
                        <CardTitle class="text-lg font-semibold text-amber-950">Étape obligatoire — votre numéro client SI</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-6">
                        <p class="text-muted-foreground text-sm">
                            Pour
                            <span class="font-medium text-foreground">{{ staff.prenom }} {{ staff.nom }}</span>
                            (réf. {{ staff.reference }}), saisissez le <strong>numéro client</strong> tel qu’il figure dans le SI (banque).
                            Nous en extrayons votre <strong>KYC</strong> et votre <strong>encours propre</strong> avant toute liaison avec
                            des personnes apparentées ou liées.
                        </p>
                        <form class="max-w-md space-y-4" @submit.prevent="submitClientSi">
                            <div>
                                <Label for="matricule_client_si">Numéro client SI *</Label>
                                <Input
                                    id="matricule_client_si"
                                    v-model="clientSiForm.matricule_client"
                                    type="text"
                                    class="mt-1.5"
                                    required
                                    autocomplete="off"
                                    placeholder="Matricule / n° client dans le SI"
                                />
                                <InputError class="mt-1" :message="clientSiForm.errors.matricule_client" />
                            </div>
                            <Button type="submit" class="bg-amber-700 hover:bg-amber-800" :disabled="clientSiForm.processing">
                                {{ clientSiForm.processing ? 'Synchronisation…' : 'Récupérer KYC et encours' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div
                    v-if="requiresClientSi && staff.personnes_liees.length > 0"
                    class="rounded-lg border border-sidebar-border bg-muted/30 p-4 text-sm"
                >
                    <p class="font-medium text-foreground">Personnes déjà liées (consultation)</p>
                    <p class="text-muted-foreground mt-1">
                        De nouvelles liaisons seront possibles après la synchronisation du numéro client SI.
                    </p>
                    <ul class="mt-2 list-inside list-disc">
                        <li v-for="pl in staff.personnes_liees" :key="pl.id">{{ libellePersonne(pl) }} — {{ pl.pivot.type_relation }}</li>
                    </ul>
                </div>

                <template v-if="!requiresClientSi">
                <p class="text-muted-foreground -mt-2 text-sm">
                    Saisissez le <strong>numéro client</strong> (matricule) : nous interrogeons le SI, mettons à jour la fiche locale si
                    besoin, puis vous indiquez la relation avec
                    <span class="font-medium text-foreground">{{ staff.prenom }} {{ staff.nom }}</span> (réf. {{ staff.reference }}).
                </p>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-lg border border-sidebar-border bg-card p-6">
                        <h2 class="mb-3 text-lg font-semibold">Encours (déclaration)</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Encours propre (SI)</dt>
                                <dd class="tabular-nums font-medium">
                                    {{ formatMontantFr(metriques?.encours_staff_si ?? Number(staff.encours_staff_si ?? 0)) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Personnes liées (Σ)</dt>
                                <dd class="tabular-nums font-medium">
                                    {{ formatMontantFr(metriques?.encours_personnes_liees ?? 0) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4 border-t pt-2 font-semibold">
                                <dt>Encours total</dt>
                                <dd class="tabular-nums">
                                    {{ formatMontantFr(metriques?.encours_total ?? Number(staff.encours_credit_individuel)) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Fonds propres (réf.)</dt>
                                <dd class="tabular-nums">
                                    {{
                                        metriques?.fonds_propres != null
                                            ? formatMontantFr(metriques.fonds_propres)
                                            : '— (à renseigner par la conformité)'
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Taux</dt>
                                <dd class="tabular-nums font-medium">
                                    {{
                                        metriques?.taux_encours_pct != null
                                            ? `${metriques.taux_encours_pct.toLocaleString('fr-FR')} %`
                                            : '—'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-lg border border-sidebar-border bg-card p-6">
                        <h2 class="mb-4 text-lg font-semibold">Contact</h2>
                        <dl class="space-y-2 text-sm">
                            <div v-if="staff.kyc_telephone">
                                <dt class="text-muted-foreground">Téléphone</dt>
                                <dd>{{ staff.kyc_telephone }}</dd>
                            </div>
                            <p v-if="!staff.kyc_telephone" class="text-muted-foreground">—</p>
                        </dl>
                    </div>
                </div>

                <Card class="border-red-100 shadow-sm">
                    <CardHeader class="border-b border-red-50 bg-gradient-to-r from-red-600/90 to-red-500/80 text-white">
                        <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                            <Link2 class="h-5 w-5" />
                            Lier une personne (numéro client → SI → liaison)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label for="matricule_pl">Numéro client</Label>
                                <Input
                                    id="matricule_pl"
                                    v-model="resolveForm.matricule"
                                    type="text"
                                    class="mt-1.5"
                                    placeholder="Matricule / n° client SI"
                                    autocomplete="off"
                                />
                            </div>
                            <div>
                                <Label for="type_client_pl">Type</Label>
                                <select
                                    id="type_client_pl"
                                    v-model="resolveForm.type_client"
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
                            :disabled="liaisonBloquee || resolving || !resolveForm.matricule.trim()"
                            @click="resolveParMatricule"
                        >
                            <Search class="mr-2 h-4 w-4" />
                            {{ resolving ? 'Vérification…' : 'Vérifier le SI et préparer la liaison' }}
                        </Button>
                        <p v-if="resolveMessage" class="text-sm text-red-600">{{ resolveMessage }}</p>

                        <template v-if="resolvedLiaison">
                            <div class="rounded-md border bg-white">
                                <div class="border-b px-4 py-2 text-sm font-semibold text-slate-700">Données SI</div>
                                <div class="divide-y text-sm">
                                    <div class="grid gap-1 px-4 py-2 md:grid-cols-[180px_1fr]">
                                        <span class="text-muted-foreground">Identité</span>
                                        <span class="font-medium">{{ resolvedLiaison.siData.prenom_nom }}</span>
                                    </div>
                                    <div v-if="resolvedLiaison.siData.adresse" class="grid gap-1 px-4 py-2 md:grid-cols-[180px_1fr]">
                                        <span class="text-muted-foreground">Adresse</span>
                                        <span>{{ resolvedLiaison.siData.adresse }}</span>
                                    </div>
                                    <div class="grid gap-1 px-4 py-2 md:grid-cols-[180px_1fr]">
                                        <span class="text-muted-foreground">Téléphone</span>
                                        <span>{{ resolvedLiaison.siData.telephone || '—' }}</span>
                                    </div>
                                    <div class="grid items-center gap-2 px-4 py-2 md:grid-cols-[180px_1fr]">
                                        <span class="text-muted-foreground">Pièce</span>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge class="bg-emerald-600 text-white hover:bg-emerald-600">{{
                                                resolvedLiaison.siData.piece_type
                                            }}</Badge>
                                            <span>{{ resolvedLiaison.siData.piece_numero || '—' }}</span>
                                        </div>
                                    </div>
                                    <div class="grid gap-1 px-4 py-2 md:grid-cols-[180px_1fr]">
                                        <span class="text-muted-foreground">Encours crédit</span>
                                        <span class="font-medium tabular-nums">{{
                                            formatMontantFr(montantEncoursApercu(resolvedLiaison))
                                        }}</span>
                                    </div>
                                    <div
                                        v-if="resolvedLiaison.siData.value_date"
                                        class="grid gap-1 px-4 py-2 md:grid-cols-[180px_1fr]"
                                    >
                                        <span class="text-muted-foreground">Date de référence</span>
                                        <span>{{ resolvedLiaison.siData.value_date }}</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm">
                                Fiche prête pour la liaison :
                                <strong>{{ libellePersonne(resolvedLiaison.personneLiee) }}</strong>
                                (n° client {{ resolvedLiaison.personneLiee.numero_client }})
                            </p>

                            <form class="grid gap-4 border-t pt-4 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="attach">
                                <div>
                                    <Label for="type_relation">Type de relation *</Label>
                                    <select
                                        id="type_relation"
                                        v-model="attachForm.type_relation"
                                        required
                                        :class="sigTypeRelationSelectClass"
                                    >
                                        <option value="">— Choisir —</option>
                                        <option v-for="t in SIG_TYPES_RELATION" :key="t" :value="t">{{ t }}</option>
                                    </select>
                                    <InputError :message="attachForm.errors.type_relation" />
                                </div>
                                <div>
                                    <Label for="classe">Classe (1–4) *</Label>
                                    <select
                                        id="classe"
                                        v-model.number="attachForm.classe"
                                        class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                                    >
                                        <option :value="1">1</option>
                                        <option :value="2">2</option>
                                        <option :value="3">3</option>
                                        <option :value="4">4</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <Button type="submit" :disabled="liaisonBloquee || attachForm.processing">
                                        Associer à ma fiche
                                    </Button>
                                </div>
                            </form>
                        </template>
                    </CardContent>
                </Card>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Personnes déjà liées</h2>

                    <div v-if="staff.personnes_liees.length === 0" class="text-muted-foreground mb-6 text-sm">
                        Aucune personne liée pour l’instant.
                    </div>
                    <div v-else class="mb-6 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="py-2 pr-4">Personne</th>
                                    <th class="py-2 pr-4">Relation</th>
                                    <th class="py-2 pr-4">Classe</th>
                                    <th class="py-2 pr-4">Encours</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pl in staff.personnes_liees" :key="pl.id" class="border-b border-gray-100">
                                    <td class="py-2 pr-4">
                                        <Link
                                            :href="`/suivi-signature/personnes-liees/${pl.id}`"
                                            class="text-primary font-medium hover:underline"
                                        >
                                            {{ libellePersonne(pl) }}
                                        </Link>
                                    </td>
                                    <td class="py-2 pr-4">{{ pl.pivot.type_relation }}</td>
                                    <td class="py-2 pr-4">Classe {{ pl.pivot.classe }}</td>
                                    <td class="py-2 pr-4">
                                        {{ Number(pl.encours_credit).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        <button
                                            type="button"
                                            class="text-destructive inline-flex rounded-md p-2 hover:bg-red-50"
                                            title="Retirer le lien"
                                            @click="detach(pl.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </template>
            </template>
        </div>
    </AppLayout>
</template>
