<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Link2, MapPin, Phone, Trash2, UserRound } from 'lucide-vue-next';
import { computed, withDefaults } from 'vue';

type StatutConformite = 'conforme' | 'alerte' | 'depassement' | 'non_evalue';

interface PersonneLieeRow {
    id: number;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    est_personne_morale: boolean;
    numero_client?: string | null;
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
    personnes_liees: PersonneLieeRow[];
}

interface SigMetriquesEncours {
    encours_staff_si: number;
    encours_personnes_liees: number;
    encours_total: number;
    fonds_propres: number | null;
    plafond_reglementaire?: number | null;
    ecart?: number | null;
    taux_encours_pct: number | null;
    seuil_taux_pct: number;
    alerte_taux_pct?: number;
    statut_conformite?: StatutConformite;
    depasse_seuil_encours: boolean;
    liaison_bloquee_encours: boolean;
}

interface Props {
    staff: Staff;
    sigMetriquesEncours?: SigMetriquesEncours | null;
    personnesDisponibles?: unknown[];
    requiresSynchronisationClientSi?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    personnesDisponibles: () => [],
});

const metriques = computed(() => props.sigMetriquesEncours);
const requiresClientSi = computed(() => Boolean(props.requiresSynchronisationClientSi));
const personnesLiees = computed(() => props.staff.personnes_liees ?? []);
const statutConformite = computed<StatutConformite>(
    () => metriques.value?.statut_conformite ?? 'non_evalue',
);

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

function formatMontantFr(n: number | null | undefined): string {
    if (n === null || n === undefined) return '—';
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatMontant(n: number | string | null | undefined): string {
    if (n === null || n === undefined || n === '') return '—';
    const v = typeof n === 'number' ? n : parseFloat(String(n).replace(',', '.'));
    if (Number.isNaN(v)) return String(n);
    return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatPct(n: number | null | undefined): string {
    if (n === null || n === undefined) return '—';
    return `${n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %`;
}

function libellePersonne(p: PersonneLieeRow): string {
    if (p.est_personne_morale && p.raison_sociale) return p.raison_sociale;
    return `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || `#${p.id}`;
}

function statutConformiteLabel(s: StatutConformite): string {
    if (s === 'conforme') return 'Conforme';
    if (s === 'alerte') return 'Alerte';
    if (s === 'depassement') return 'Dépassement';
    return 'Non évalué';
}

const detach = (personneLieeId: number) => {
    if (!confirm('Retirer ce lien pour ce signataire uniquement ?')) return;
    router.delete(`/suivi-signature/staff/${props.staff.id}/personnes-liees/${personneLieeId}`, {
        preserveScroll: true,
    });
};

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
    { title: `${props.staff.prenom} ${props.staff.nom}`.trim() || props.staff.reference, href: '#' },
];

const typeLabel = (t: string) =>
    ({ staff: 'Staff', administrateur: 'Administrateur', apparente_ou_liee: 'Apparentée / liée' } as Record<string, string>)[t] || t;

const matriculeAffiche = computed(
    () => trimOrDash(props.staff.numero_client_si) !== '—'
        ? String(props.staff.numero_client_si)
        : props.staff.reference,
);

function trimOrDash(v: string | null | undefined): string {
    const t = (v ?? '').trim();
    return t !== '' ? t : '—';
}

const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);
</script>

<template>
    <Head :title="`Staff ${staff.prenom} ${staff.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900">
                            {{ staff.prenom }} {{ staff.nom }}
                        </h1>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                            {{ typeLabel(staff.type_personne) }}
                        </span>
                        <span
                            class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="
                                staff.statut === 'actif'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                    : 'border-slate-200 bg-slate-50 text-slate-600'
                            "
                        >
                            {{ staff.statut === 'actif' ? 'Actif' : 'Inactif' }}
                        </span>
                        <span
                            class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="{
                                'border-emerald-200 bg-emerald-50 text-emerald-800': statutConformite === 'conforme',
                                'border-orange-200 bg-orange-50 text-orange-800': statutConformite === 'alerte',
                                'border-red-200 bg-red-50 text-red-800': statutConformite === 'depassement',
                                'border-gray-200 bg-gray-50 text-gray-700': statutConformite === 'non_evalue',
                            }"
                        >
                            {{ statutConformiteLabel(statutConformite) }}
                        </span>
                    </div>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Matricule
                        <span class="font-mono text-slate-700">{{ matriculeAffiche }}</span>
                        <template v-if="staff.fonction"> · {{ staff.fonction }}</template>
                        <template v-if="staff.departement"> · {{ staff.departement }}</template>
                    </p>
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
            <div
                v-if="flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"
            >
                {{ flash.error }}
            </div>

            <div
                v-if="requiresClientSi"
                class="rounded-xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm"
            >
                <h2 class="text-base font-semibold text-amber-950">Numéro client SI requis</h2>
                <p class="mt-1 text-sm text-amber-900/80">
                    Saisissez le n° client SI pour charger le KYC et l’encours propre avant d’associer des
                    personnes liées.
                </p>
                <form class="mt-4 max-w-md space-y-3" @submit.prevent="submitClientSi">
                    <div>
                        <Label for="matricule_client_si_show">Numéro client SI *</Label>
                        <Input
                            id="matricule_client_si_show"
                            v-model="clientSiForm.matricule_client"
                            type="text"
                            class="mt-1.5"
                            required
                            autocomplete="off"
                            placeholder="CUSTOMER_NO / n° client SI"
                        />
                        <InputError class="mt-1" :message="clientSiForm.errors.matricule_client" />
                    </div>
                    <Button type="submit" class="bg-amber-700 hover:bg-amber-800" :disabled="clientSiForm.processing">
                        {{ clientSiForm.processing ? 'Synchronisation…' : 'Récupérer KYC et encours' }}
                    </Button>
                </form>
            </div>

            <div
                v-if="metriques?.depasse_seuil_encours"
                class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-950"
            >
                <p class="font-semibold">Seuil d’encours dépassé</p>
                <p class="mt-1">
                    Ratio {{ formatPct(metriques.taux_encours_pct) }} (seuil {{ formatPct(metriques.seuil_taux_pct) }}).
                    <span v-if="metriques.liaison_bloquee_encours"> Nouvelles liaisons bloquées.</span>
                </p>
            </div>

            <!-- Métriques réglementaires -->
            <section class="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
                <div class="border-b border-sidebar-border bg-muted/40 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-900">Situation réglementaire</h2>
                    <p class="text-muted-foreground text-xs">
                        Encours consolidé vs plafond (seuil {{ formatPct(metriques?.seuil_taux_pct) }})
                    </p>
                </div>
                <div class="grid gap-px bg-sidebar-border sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Encours staff
                        </p>
                        <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900">
                            {{ formatMontantFr(metriques?.encours_staff_si ?? Number(staff.encours_staff_si || 0)) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Encours liés
                        </p>
                        <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900">
                            {{ formatMontantFr(metriques?.encours_personnes_liees ?? 0) }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-[11px]">
                            {{ personnesLiees.length }} personne{{ personnesLiees.length > 1 ? 's' : '' }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-[11px] font-medium tracking-wide text-primary/80 uppercase">Encours total</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums text-primary">
                            {{
                                formatMontantFr(
                                    metriques?.encours_total ?? Number(staff.encours_credit_individuel || 0),
                                )
                            }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Plafond
                        </p>
                        <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900">
                            {{ formatMontantFr(metriques?.plafond_reglementaire) }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-[11px]">
                            FP {{ formatMontantFr(metriques?.fonds_propres) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Ratio</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900">
                            {{ formatPct(metriques?.taux_encours_pct) }}
                        </p>
                    </div>
                    <div class="bg-card p-4">
                        <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                            Écart vs plafond
                        </p>
                        <p
                            class="mt-2 text-xl font-semibold tabular-nums"
                            :class="
                                metriques?.ecart != null && metriques.ecart < 0 ? 'text-red-600' : 'text-emerald-700'
                            "
                        >
                            {{ formatMontantFr(metriques?.ecart) }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Identité + KYC -->
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border border-sidebar-border bg-card p-5 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <UserRound class="h-4 w-4 text-slate-500" />
                        <h2 class="text-sm font-semibold text-gray-900">Identité</h2>
                    </div>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground text-xs">Référence</dt>
                            <dd class="mt-0.5 font-mono font-medium text-gray-900">{{ staff.reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">N° client SI</dt>
                            <dd class="mt-0.5 font-mono font-medium text-gray-900">
                                {{ trimOrDash(staff.numero_client_si) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Type</dt>
                            <dd class="mt-0.5 text-gray-900">{{ typeLabel(staff.type_personne) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Statut fiche</dt>
                            <dd class="mt-0.5 text-gray-900">
                                {{ staff.statut === 'actif' ? 'Actif' : 'Inactif' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Fonction</dt>
                            <dd class="mt-0.5 text-gray-900">{{ trimOrDash(staff.fonction) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Département</dt>
                            <dd class="mt-0.5 text-gray-900">{{ trimOrDash(staff.departement) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-sidebar-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">KYC</h2>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground text-xs">Pièce d’identité</dt>
                            <dd class="mt-0.5 font-medium text-gray-900">
                                {{ trimOrDash(staff.kyc_piece_identite) }}
                            </dd>
                        </div>
                        <div class="flex items-start gap-2">
                            <Phone class="text-muted-foreground mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <div>
                                <dt class="text-muted-foreground text-xs">Téléphone</dt>
                                <dd class="mt-0.5 text-gray-900">{{ trimOrDash(staff.kyc_telephone) }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <MapPin class="text-muted-foreground mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <div>
                                <dt class="text-muted-foreground text-xs">Adresse</dt>
                                <dd class="mt-0.5 whitespace-pre-wrap text-gray-900">
                                    {{ trimOrDash(staff.kyc_adresse) }}
                                </dd>
                            </div>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- Personnes liées -->
            <section class="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sidebar-border px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Link2 class="h-4 w-4 text-slate-500" />
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Personnes liées</h2>
                            <p class="text-muted-foreground text-xs">
                                {{ personnesLiees.length }} liaison{{ personnesLiees.length > 1 ? 's' : '' }}
                                · encours {{ formatMontantFr(metriques?.encours_personnes_liees ?? 0) }}
                            </p>
                        </div>
                    </div>
                    <Link :href="`/suivi-signature/staff/${staff.id}/lier-personnes`">
                        <Button type="button" size="sm" variant="outline" class="gap-1.5">
                            <Link2 class="h-3.5 w-3.5" />
                            Gérer les liaisons
                        </Button>
                    </Link>
                </div>

                <div v-if="personnesLiees.length === 0" class="text-muted-foreground px-4 py-8 text-center text-sm">
                    Aucune personne liée à ce signataire.
                    <Link
                        :href="`/suivi-signature/staff/${staff.id}/lier-personnes`"
                        class="text-primary ml-1 font-medium hover:underline"
                    >
                        Ajouter une personne
                    </Link>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Personne</th>
                                <th class="px-4 py-2.5 font-medium">Relation</th>
                                <th class="px-4 py-2.5 font-medium">Classe</th>
                                <th class="px-4 py-2.5 text-right font-medium">Encours</th>
                                <th class="px-4 py-2.5 text-right font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="pl in personnesLiees"
                                :key="pl.id"
                                class="border-b border-gray-100 hover:bg-muted/20"
                            >
                                <td class="px-4 py-2.5">
                                    <Link
                                        :href="`/suivi-signature/personnes-liees/${pl.id}`"
                                        class="font-medium text-slate-900 underline-offset-2 hover:text-primary hover:underline"
                                    >
                                        {{ libellePersonne(pl) }}
                                    </Link>
                                    <div v-if="pl.numero_client" class="font-mono text-xs text-slate-400">
                                        {{ pl.numero_client }}
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700">{{ pl.pivot.type_relation }}</td>
                                <td class="px-4 py-2.5 text-slate-700">Classe {{ pl.pivot.classe }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-800">
                                    {{ formatMontant(pl.encours_credit) }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex rounded-md p-2 text-red-600 hover:bg-red-50"
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
            </section>
        </div>
    </AppLayout>
</template>
