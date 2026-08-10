<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Code, Link2 } from 'lucide-vue-next';
import { computed, withDefaults } from 'vue';

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
    personnes_liees: { id: number }[];
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

interface ConformiteEncoursEventItem {
    id: number;
    type: string;
    type_label: string;
    created_at: string;
    fonds_propres: string | null;
    encours_consolide: string;
    taux_pct: string | null;
    seuil_pct: string;
    commentaire: string | null;
    user: { name: string; email: string } | null;
}

interface Props {
    staff: Staff;
    sigMetriquesEncours?: SigMetriquesEncours | null;
    personnesDisponibles?: unknown[];
    requiresSynchronisationClientSi?: boolean;
    conformiteEncoursHistorique?: ConformiteEncoursEventItem[];
    peutCommenterConformiteEncours?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    personnesDisponibles: () => [],
    conformiteEncoursHistorique: () => [],
    peutCommenterConformiteEncours: false,
});

const metriques = computed(() => props.sigMetriquesEncours);
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

function formatMontantFr(n: number): string {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
    { title: props.staff.reference, href: '#' },
];

const typeLabel = (t: string) =>
    ({ staff: 'Staff', administrateur: 'Administrateur', apparente_ou_liee: 'Apparentée / liée' } as Record<string, string>)[t] || t;

const afficheBlocConformiteEncours = computed(
    () => props.peutCommenterConformiteEncours || (props.conformiteEncoursHistorique?.length ?? 0) > 0,
);

const conformiteCommentForm = useForm({
    commentaire: '',
});

function submitConformiteComment() {
    conformiteCommentForm.post(`/suivi-signature/staff/${props.staff.id}/conformite-encours/commentaire`, {
        preserveScroll: true,
        onSuccess: () => conformiteCommentForm.reset(),
    });
}

const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);

function formatMontantStr(v: string | null): string {
    if (v === null || v === '') return '—';
    const n = parseFloat(String(v).replace(',', '.'));
    if (Number.isNaN(n)) return v;
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function badgeClassConformite(type: string): string {
    if (type === 'depassement') return 'bg-red-600 text-white';
    if (type === 'retour_conforme') return 'bg-emerald-600 text-white';
    return 'bg-slate-600 text-white';
}
</script>

<template>
    <Head :title="`Staff ${staff.reference}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ staff.prenom }} {{ staff.nom }}</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="`/suivi-signature/staff/${staff.id}/lier-personnes`">
                        <Button type="button">
                            <Link2 class="mr-1.5 h-4 w-4" />
                            Lier
                        </Button>
                    </Link>
                    <Link :href="`/suivi-signature/staff/${staff.id}/edit`">
                        <Button variant="outline">Modifier</Button>
                    </Link>
                    <Link href="/suivi-signature/staff">
                        <Button variant="secondary">Liste</Button>
                    </Link>
                </div>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900"
            >
                {{ flash.success }}
            </div>

            <Card v-if="requiresClientSi" class="border-amber-200 shadow-sm">
                <CardHeader class="border-b border-amber-100 bg-amber-50/90">
                    <CardTitle class="text-lg font-semibold text-amber-950">Numéro client SI requis</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 pt-6">
                    <p class="text-muted-foreground text-sm">
                        Saisissez votre <strong>numéro client</strong> dans le SI pour charger le KYC et l’encours propre avant d’associer de
                        nouvelles personnes liées.
                    </p>
                    <form class="max-w-md space-y-4" @submit.prevent="submitClientSi">
                        <div>
                            <Label for="matricule_client_si_show">Numéro client SI *</Label>
                            <Input
                                id="matricule_client_si_show"
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
                v-if="metriques?.depasse_seuil_encours"
                class="rounded-lg border-2 border-red-600 bg-red-50 p-4 text-sm text-red-900"
            >
                <p class="font-semibold">Alerte — seuil d’encours dépassé</p>
                <p class="mt-1">
                    Taux : <strong>{{ metriques.taux_encours_pct?.toLocaleString('fr-FR') }} %</strong> (seuil
                    {{ metriques.seuil_taux_pct.toLocaleString('fr-FR') }} %).
                    <span v-if="metriques.liaison_bloquee_encours"> Nouvelles liaisons bloquées.</span>
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Fiche réglementaire</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Référence</dt>
                            <dd class="font-mono font-medium">{{ staff.reference }}</dd>
                        </div>
                        <div v-if="staff.numero_client_si" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">N° client SI</dt>
                            <dd class="font-mono font-medium">{{ staff.numero_client_si }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Type</dt>
                            <dd>{{ typeLabel(staff.type_personne) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Statut</dt>
                            <dd>{{ staff.statut === 'actif' ? 'Actif' : 'Inactif' }}</dd>
                        </div>
                        <div v-if="staff.fonction" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Fonction</dt>
                            <dd>{{ staff.fonction }}</dd>
                        </div>
                        <div v-if="staff.departement" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Département</dt>
                            <dd>{{ staff.departement }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Encours propre (SI)</dt>
                            <dd class="tabular-nums">
                                {{ formatMontantFr(metriques?.encours_staff_si ?? Number(staff.encours_staff_si ?? 0)) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Personnes liées (Σ)</dt>
                            <dd class="tabular-nums">{{ formatMontantFr(metriques?.encours_personnes_liees ?? 0) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 font-semibold">
                            <dt>Encours total</dt>
                            <dd class="tabular-nums">
                                {{ formatMontantFr(metriques?.encours_total ?? Number(staff.encours_credit_individuel)) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Fonds propres</dt>
                            <dd class="tabular-nums">
                                {{
                                    metriques?.fonds_propres != null
                                        ? formatMontantFr(metriques.fonds_propres)
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Taux encours / FP</dt>
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
                    <h2 class="mb-4 text-lg font-semibold">KYC</h2>
                    <dl class="space-y-2 text-sm">
                        <div v-if="staff.kyc_piece_identite">
                            <dt class="text-muted-foreground">Pièce d’identité</dt>
                            <dd>{{ staff.kyc_piece_identite }}</dd>
                        </div>
                        <div v-if="staff.kyc_telephone">
                            <dt class="text-muted-foreground">Téléphone</dt>
                            <dd>{{ staff.kyc_telephone }}</dd>
                        </div>
                        <div v-if="staff.kyc_adresse">
                            <dt class="text-muted-foreground">Adresse</dt>
                            <dd class="whitespace-pre-wrap">{{ staff.kyc_adresse }}</dd>
                        </div>
                        <p v-if="!staff.kyc_piece_identite && !staff.kyc_telephone && !staff.kyc_adresse" class="text-muted-foreground">
                            Aucune donnée KYC saisie.
                        </p>
                    </dl>
                </div>
            </div>

            <Card v-if="afficheBlocConformiteEncours" class="border-slate-200 shadow-sm">
                <CardHeader class="border-b bg-slate-50/80">
                    <CardTitle class="flex flex-wrap items-center justify-between gap-2 text-lg font-semibold">
                        <span>Conformité — historique encours / fonds propres</span>
                        <Link
                            :href="`/suivi-signature/conformite/rapport-encours?staff_id=${staff.id}`"
                            class="text-primary text-sm font-medium underline"
                        >
                            Rapport & export
                        </Link>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-6 pt-6">
                    <p class="text-muted-foreground text-sm">
                        Les dépassements de seuil et les retours sous le seuil sont enregistrés automatiquement à chaque recalcul des encours.
                        Vous pouvez ajouter une justification ou un commentaire de traitement.
                    </p>
                    <div v-if="conformiteEncoursHistorique.length === 0" class="text-muted-foreground text-sm">
                        Aucun événement enregistré pour cette fiche.
                    </div>
                    <ul v-else class="space-y-4">
                        <li
                            v-for="ev in conformiteEncoursHistorique"
                            :key="ev.id"
                            class="rounded-md border border-sidebar-border bg-card p-4 text-sm"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded px-2 py-0.5 text-xs font-medium"
                                    :class="badgeClassConformite(ev.type)"
                                >
                                    {{ ev.type_label }}
                                </span>
                                <span class="text-muted-foreground">{{ ev.created_at }}</span>
                            </div>
                            <dl class="mt-2 grid gap-1 text-xs sm:grid-cols-2">
                                <div>
                                    <dt class="text-muted-foreground">Fonds propres (snap.)</dt>
                                    <dd class="tabular-nums font-medium">{{ formatMontantStr(ev.fonds_propres) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Encours consolidé</dt>
                                    <dd class="tabular-nums font-medium">{{ formatMontantStr(ev.encours_consolide) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Taux / seuil</dt>
                                    <dd class="tabular-nums">
                                        {{ ev.taux_pct != null ? `${formatMontantStr(ev.taux_pct)} %` : '—' }} /
                                        {{ formatMontantStr(ev.seuil_pct) }} %
                                    </dd>
                                </div>
                                <div v-if="ev.user">
                                    <dt class="text-muted-foreground">Auteur</dt>
                                    <dd>{{ ev.user.name || ev.user.email }}</dd>
                                </div>
                            </dl>
                            <p v-if="ev.commentaire" class="mt-2 whitespace-pre-wrap rounded bg-muted/40 p-2 text-xs">
                                {{ ev.commentaire }}
                            </p>
                        </li>
                    </ul>
                    <form
                        v-if="peutCommenterConformiteEncours"
                        class="max-w-2xl space-y-3 border-t pt-4"
                        @submit.prevent="submitConformiteComment"
                    >
                        <Label for="conformite_comment">Justification / commentaire conformité</Label>
                        <textarea
                            id="conformite_comment"
                            v-model="conformiteCommentForm.commentaire"
                            required
                            rows="4"
                            class="border-input bg-background mt-1.5 flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm shadow-sm"
                            placeholder="Mesure prise, analyse, référence dossier…"
                        />
                        <InputError class="mt-1" :message="conformiteCommentForm.errors.commentaire" />
                        <Button type="submit" :disabled="conformiteCommentForm.processing">
                            {{ conformiteCommentForm.processing ? 'Enregistrement…' : 'Enregistrer dans l’historique' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
