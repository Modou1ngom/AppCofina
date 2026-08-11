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
import { Code, Link2, Plus, Search, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref, withDefaults } from 'vue';
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

interface Dispo {
    id: number;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    est_personne_morale: boolean;
    numero_client?: string | null;
    encours_credit?: string;
}

interface Staff {
    id: number;
    reference: string;
    prenom: string;
    nom: string;
    personnes_liees: PersonnePivot[];
}

interface SigMetriquesEncours {
    encours_personnes_liees: number;
    encours_total: number;
    liaison_bloquee_encours: boolean;
}

interface SiData {
    matricule: string;
    type_client: string;
    prenom_nom: string;
    adresse: string | null;
    telephone: string | null;
    piece_type: string;
    piece_numero: string | null;
    encours_total?: number | string | null;
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

interface SuggestionSi {
    numero_client: string;
    prenom_nom: string;
    prenom?: string | null;
    nom?: string | null;
    raison_sociale?: string | null;
    est_personne_morale: boolean;
    type_relation: string;
    classe: number;
    detail_relation?: string | null;
    kyc_staff?: string | null;
    kyc_staff_piece?: string | null;
}

interface Props {
    staff: Staff;
    sigMetriquesEncours?: SigMetriquesEncours | null;
    personnesDisponibles: Dispo[];
    peutCreerFichePersonneLiee?: boolean;
    peutResoudreSi?: boolean;
    suggestionsSi?: SuggestionSi[];
    cleDetectionSi?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    peutCreerFichePersonneLiee: false,
    peutResoudreSi: false,
    suggestionsSi: () => [],
    cleDetectionSi: null,
});

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);
const liaisonBloquee = computed(() => Boolean(props.sigMetriquesEncours?.liaison_bloquee_encours));
const afficherAjout = ref(false);
const afficherDetection = ref(false);

const peutAjouter = computed(
    () =>
        !liaisonBloquee.value &&
        (props.peutResoudreSi || props.personnesDisponibles.length > 0 || props.peutCreerFichePersonneLiee),
);

const detecterForm = useForm({});
const lancerDetection = () => {
    detecterForm.post(`/suivi-signature/staff/${props.staff.id}/personnes-liees/detecter-auto`, {
        preserveScroll: true,
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
    { title: props.staff.reference, href: `/suivi-signature/staff/${props.staff.id}` },
    { title: 'Lier des personnes', href: '#' },
];

const libellePersonne = (p: Dispo | PersonnePivot | PersonneResolue) => {
    if (p.est_personne_morale && p.raison_sociale) return p.raison_sociale;
    return `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || `#${p.id}`;
};

const attachForm = useForm({
    sig_personne_liee_id: '' as number | '',
    type_relation: '',
    classe: 1 as number,
});

const attach = () => {
    attachForm.post(`/suivi-signature/staff/${props.staff.id}/personnes-liees`, {
        preserveScroll: true,
        onSuccess: () => {
            attachForm.reset();
            attachForm.classe = 1;
            resolvedLiaison.value = null;
            resolveMessage.value = '';
            afficherAjout.value = false;
        },
    });
};

const detach = (personneLieeId: number) => {
    if (!confirm('Retirer ce lien pour ce signataire uniquement ?')) return;
    router.delete(`/suivi-signature/staff/${props.staff.id}/personnes-liees/${personneLieeId}`, {
        preserveScroll: true,
    });
};

const resolveForm = ref({
    matricule: '',
    type_client: 'personnel' as 'personnel' | 'entreprise',
});
const resolving = ref(false);
const resolveMessage = ref('');
const resolvedLiaison = ref<{ siData: SiData; personneLiee: PersonneResolue } | null>(null);

const resolveParMatricule = async () => {
    resolveMessage.value = '';
    resolvedLiaison.value = null;
    resolving.value = true;
    try {
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content;
        const res = await fetch('/suivi-signature/personne-liee/resolve-matricule', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                matricule: resolveForm.value.matricule.trim(),
                type_client: resolveForm.value.type_client,
            }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            resolveMessage.value = data.message || 'Impossible de résoudre ce numéro client dans le SI.';
            return;
        }
        if (props.staff.personnes_liees.some((p) => p.id === data.personneLiee.id)) {
            resolveMessage.value = 'Cette personne est déjà liée à ce signataire.';
            return;
        }
        resolvedLiaison.value = { siData: data.siData, personneLiee: data.personneLiee };
        attachForm.sig_personne_liee_id = data.personneLiee.id;
    } catch {
        resolveMessage.value = 'Erreur réseau lors de la résolution SI.';
    } finally {
        resolving.value = false;
    }
};

const attachResolu = () => {
    if (!resolvedLiaison.value) return;
    attachForm.sig_personne_liee_id = resolvedLiaison.value.personneLiee.id;
    attach();
};

function formatMontant(n: number | string | null | undefined): string {
    if (n === null || n === undefined || n === '') return '—';
    const v = typeof n === 'number' ? n : parseFloat(String(n).replace(',', '.'));
    if (Number.isNaN(v)) return String(n);
    return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head :title="`Lier — ${staff.reference}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-4xl flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold text-gray-900">Lier des personnes</h1>
                        <Code class="h-5 w-5 text-gray-500" />
                    </div>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Personnes liées au signataire
                        <strong>{{ staff.prenom }} {{ staff.nom }}</strong>
                        (réf. {{ staff.reference }}).
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="!liaisonBloquee && cleDetectionSi"
                        type="button"
                        variant="outline"
                        @click="afficherDetection = !afficherDetection"
                    >
                        <Sparkles class="mr-1.5 h-4 w-4" />
                        Détection auto
                        <span v-if="suggestionsSi.length" class="ml-1.5 text-xs">({{ suggestionsSi.length }})</span>
                    </Button>
                    <Button
                        v-if="peutAjouter && !afficherAjout"
                        type="button"
                        @click="afficherAjout = true"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        Ajouter une personne
                    </Button>
                    <Button
                        v-if="afficherAjout"
                        type="button"
                        variant="outline"
                        @click="afficherAjout = false"
                    >
                        Masquer l’ajout
                    </Button>
                    <Link :href="`/suivi-signature/staff/${staff.id}`">
                        <Button variant="secondary">Retour à la fiche</Button>
                    </Link>
                </div>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error || liaisonBloquee"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"
            >
                <p v-if="flash?.error">{{ flash.error }}</p>
                <p v-if="liaisonBloquee">Nouvelles liaisons bloquées : seuil d’encours dépassé pour ce signataire.</p>
            </div>

            <Card v-if="afficherDetection && !liaisonBloquee">
                <CardHeader>
                    <CardTitle class="text-lg">Détection automatique (SI)</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-muted-foreground text-sm">
                        Clients liés à ce staff via <strong>caution</strong> (STDCOLAT) ou
                        <strong>cotitulaire</strong> (compte class 25136), à partir du
                        <strong>n° client SI</strong>
                        <span class="font-mono">{{ cleDetectionSi }}</span>
                        (CUSTOMER_NO — pas le n° de pièce d’identité).
                    </p>
                    <div v-if="suggestionsSi.length === 0" class="text-muted-foreground text-sm">
                        Aucun nouveau client détecté (ou déjà tous liés). Vous pouvez relancer la liaison automatique.
                    </div>
                    <ul v-else class="divide-y rounded-md border bg-white text-sm">
                        <li
                            v-for="s in suggestionsSi"
                            :key="s.numero_client"
                            class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <span class="font-medium text-gray-900">{{ s.prenom_nom }}</span>
                                <span class="text-muted-foreground"> — {{ s.numero_client }}</span>
                            </div>
                            <div class="text-slate-600">
                                {{ s.type_relation }}
                                <template v-if="s.detail_relation"> · {{ s.detail_relation }}</template>
                                · classe {{ s.classe }}
                            </div>
                        </li>
                    </ul>
                    <Button
                        type="button"
                        :disabled="detecterForm.processing || !cleDetectionSi"
                        @click="lancerDetection"
                    >
                        <Sparkles class="mr-1.5 h-4 w-4" />
                        {{
                            detecterForm.processing
                                ? 'Détection…'
                                : suggestionsSi.length
                                  ? `Lier les ${suggestionsSi.length} client(s) à ce signataire`
                                  : 'Relancer la détection et lier'
                        }}
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Link2 class="h-5 w-5" />
                        Déjà liées à ce signataire
                        <span class="text-muted-foreground text-sm font-normal">
                            ({{ staff.personnes_liees.length }})
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="staff.personnes_liees.length === 0" class="text-muted-foreground text-sm">
                        Aucune personne liée à ce signataire pour l’instant.
                        <button
                            v-if="peutAjouter"
                            type="button"
                            class="text-primary ml-1 underline"
                            @click="afficherAjout = true"
                        >
                            Ajouter une personne
                        </button>
                    </div>
                    <div v-else class="overflow-x-auto">
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
                                    <td class="py-2 pr-4 tabular-nums">{{ formatMontant(pl.encours_credit) }}</td>
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
                </CardContent>
            </Card>

            <template v-if="afficherAjout && !liaisonBloquee">
                <Card v-if="peutResoudreSi">
                    <CardHeader>
                        <CardTitle class="text-lg">Lier une personne (recherche SI)</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-muted-foreground text-sm">
                            Saisissez le n° client SI : la fiche est créée ou mise à jour, puis associée
                            <strong>uniquement</strong> à {{ staff.prenom }} {{ staff.nom }}.
                        </p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label for="matricule_lier">Numéro client SI</Label>
                                <Input
                                    id="matricule_lier"
                                    v-model="resolveForm.matricule"
                                    type="text"
                                    class="mt-1.5"
                                    placeholder="N° client"
                                    autocomplete="off"
                                />
                            </div>
                            <div>
                                <Label for="type_client_lier">Type</Label>
                                <select
                                    id="type_client_lier"
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
                            :disabled="resolving || !resolveForm.matricule.trim()"
                            @click="resolveParMatricule"
                        >
                            <Search class="mr-2 h-4 w-4" />
                            {{ resolving ? 'Vérification…' : 'Vérifier le SI' }}
                        </Button>
                        <p v-if="resolveMessage" class="text-sm text-red-600">{{ resolveMessage }}</p>

                        <template v-if="resolvedLiaison">
                            <div class="rounded-md border bg-white text-sm">
                                <div class="border-b px-4 py-2 font-semibold">Données SI</div>
                                <div class="grid gap-1 px-4 py-2 md:grid-cols-[160px_1fr]">
                                    <span class="text-muted-foreground">Identité</span>
                                    <span class="font-medium">{{ resolvedLiaison.siData.prenom_nom }}</span>
                                </div>
                                <div class="grid gap-1 border-t px-4 py-2 md:grid-cols-[160px_1fr]">
                                    <span class="text-muted-foreground">Encours</span>
                                    <span class="tabular-nums">{{
                                        formatMontant(
                                            resolvedLiaison.siData.encours_total ??
                                                resolvedLiaison.personneLiee.encours_credit,
                                        )
                                    }}</span>
                                </div>
                                <div class="grid gap-1 border-t px-4 py-2 md:grid-cols-[160px_1fr]">
                                    <span class="text-muted-foreground">Pièce</span>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge class="bg-emerald-600 text-white hover:bg-emerald-600">{{
                                            resolvedLiaison.siData.piece_type
                                        }}</Badge>
                                        <span>{{ resolvedLiaison.siData.piece_numero || '—' }}</span>
                                    </div>
                                </div>
                            </div>
                            <form class="grid gap-4 md:grid-cols-3" @submit.prevent="attachResolu">
                                <div>
                                    <Label for="type_relation_si">Type de relation *</Label>
                                    <select
                                        id="type_relation_si"
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
                                    <Label for="classe_si">Classe (1–4) *</Label>
                                    <select
                                        id="classe_si"
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
                                    <Button type="submit" :disabled="attachForm.processing">
                                        Associer à ce signataire
                                    </Button>
                                </div>
                            </form>
                        </template>
                    </CardContent>
                </Card>

                <Card v-if="personnesDisponibles.length > 0">
                    <CardHeader>
                        <CardTitle class="text-lg">Associer une fiche déjà existante</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="attach">
                            <div class="md:col-span-2">
                                <Label for="sig_personne_liee_id">Personne *</Label>
                                <select
                                    id="sig_personne_liee_id"
                                    v-model="attachForm.sig_personne_liee_id"
                                    required
                                    class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                                >
                                    <option value="">— Choisir —</option>
                                    <option v-for="p in personnesDisponibles" :key="p.id" :value="p.id">
                                        {{ libellePersonne(p) }}
                                        <template v-if="p.numero_client"> ({{ p.numero_client }})</template>
                                    </option>
                                </select>
                                <InputError :message="attachForm.errors.sig_personne_liee_id" />
                            </div>
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
                            <div class="flex items-end md:col-span-2 lg:col-span-4">
                                <Button type="submit" :disabled="attachForm.processing">Associer à ce signataire</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <p v-if="peutCreerFichePersonneLiee" class="text-muted-foreground text-sm">
                    Besoin d’une nouvelle fiche avant association ?
                    <Link
                        :href="`/suivi-signature/personnes-liees/create?staff_id=${staff.id}`"
                        class="text-primary underline"
                    >
                        Créer puis lier à {{ staff.prenom }} {{ staff.nom }}
                    </Link>
                </p>
            </template>
        </div>
    </AppLayout>
</template>
