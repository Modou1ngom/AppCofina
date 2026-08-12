<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { BadgeCheck, CheckCircle2, Link2, Radar, Search, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { SIG_TYPES_RELATION, sigTypeRelationSelectClass } from '@/lib/sigTypeRelation';

interface Ligne {
    nom_staff: string;
    encours_staff: number;
    matricule_staff: string;
    type_piece_staff: string;
    numero_piece_staff: string;
    telephone_staff: string;
    nom_personne_liee: string;
    matricule_personnel_lie: string;
    encours_personne_liee: number;
    type_liaison: string;
    type_relation?: string | null;
    detail_liaison?: string | null;
    staff_local_id?: number | null;
    deja_lie?: boolean;
}

interface Props {
    lignes: Ligne[];
    filters: { search: string; action?: string };
    counts?: { total: number; a_lier: number; deja_lie: number };
    total: number;
}

const props = defineProps<Props>();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);

const searchLocal = ref(props.filters.search || '');
const actionLocal = ref(props.filters.action || '');
const dialogOpen = ref(false);
const ligneSelectionnee = ref<Ligne | null>(null);

watch(
    () => props.filters,
    (f) => {
        searchLocal.value = f.search || '';
        actionLocal.value = f.action || '';
    },
    { deep: true },
);

const counts = computed(() => ({
    total: props.counts?.total ?? props.total,
    a_lier: props.counts?.a_lier ?? 0,
    deja_lie: props.counts?.deja_lie ?? 0,
}));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suivi signature', href: '/suivi-signature/staff' },
    { title: 'Détection automatique', href: '#' },
];

function formatMontant(n: number): string {
    return Number(n || 0).toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function initials(name: string): string {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function badgeLiaison(type: string): string {
    const t = (type || '').toLowerCase();
    if (t.includes('préciser') || t.includes('preciser') || t === '—' || t === '') {
        return 'border-amber-200 bg-amber-50 text-amber-800';
    }
    if (t.includes('caution')) return 'border-amber-200 bg-amber-50 text-amber-800';
    if (t.includes('cotitul')) return 'border-sky-200 bg-sky-50 text-sky-800';
    return 'border-slate-200 bg-slate-50 text-slate-700';
}

function libelleTypeRelation(l: Ligne): string {
    if (l.deja_lie && l.type_relation?.trim()) {
        return l.type_relation.trim();
    }
    const t = (l.type_liaison || '').trim();
    if (!t || t === '—' || /^détection\s*auto$/i.test(t)) {
        return 'À préciser';
    }
    return t;
}

const applyFilters = (overrideAction?: string) => {
    if (overrideAction !== undefined) {
        actionLocal.value = overrideAction;
    }
    const p = new URLSearchParams();
    if (searchLocal.value.trim()) p.set('search', searchLocal.value.trim());
    if (actionLocal.value) p.set('action', actionLocal.value);
    router.get(`/suivi-signature/detection-automatique?${p.toString()}`, {}, { preserveState: true });
};

const lierForm = useForm({
    matricule_staff: '',
    matricule_personnel_lie: '',
    type_relation: '',
    classe: 2 as number,
    complement: '',
    nom_staff: '',
    telephone_staff: '',
    type_piece_staff: '',
    numero_piece_staff: '',
    encours_staff: 0 as number,
    nom_personne_liee: '',
});

function ouvrirLier(ligne: Ligne) {
    if (ligne.deja_lie) return;
    ligneSelectionnee.value = ligne;
    lierForm.matricule_staff = ligne.matricule_staff;
    lierForm.matricule_personnel_lie = ligne.matricule_personnel_lie;
    const siType = (ligne.type_liaison || '').trim();
    const match = SIG_TYPES_RELATION.find((t) => t.toLowerCase() === siType.toLowerCase());
    lierForm.type_relation = match ?? '';
    lierForm.classe = 2;
    lierForm.complement = match || !siType || siType === '—' ? '' : siType;
    lierForm.nom_staff = ligne.nom_staff || '';
    lierForm.telephone_staff = ligne.telephone_staff || '';
    lierForm.type_piece_staff = ligne.type_piece_staff || '';
    lierForm.numero_piece_staff = ligne.numero_piece_staff || '';
    lierForm.encours_staff = Number(ligne.encours_staff || 0);
    lierForm.nom_personne_liee = ligne.nom_personne_liee || '';
    lierForm.clearErrors();
    dialogOpen.value = true;
}

function fermerDialog() {
    if (lierForm.processing) return;
    dialogOpen.value = false;
    ligneSelectionnee.value = null;
}

function validerLiaison() {
    if (!ligneSelectionnee.value) return;

    lierForm
        .transform((data) => ({
            matricule_staff: data.matricule_staff,
            matricule_personnel_lie: data.matricule_personnel_lie,
            type_relation: data.type_relation,
            complement: data.complement?.trim() || null,
            classe: data.classe,
            nom_staff: data.nom_staff,
            telephone_staff: data.telephone_staff,
            type_piece_staff: data.type_piece_staff,
            numero_piece_staff: data.numero_piece_staff,
            encours_staff: data.encours_staff,
            nom_personne_liee: data.nom_personne_liee,
        }))
        .post('/suivi-signature/detection-automatique/lier', {
            preserveScroll: true,
            onSuccess: () => {
                dialogOpen.value = false;
                ligneSelectionnee.value = null;
                lierForm.reset();
                lierForm.classe = 2;
            },
            onFinish: () => {
                lierForm.transform((data) => data);
            },
        });
}
</script>

<template>
    <Head title="Détection automatique — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- En-tête : même format (titre + 3 KPI) -->
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3">
                    <Radar class="mt-0.5 h-7 w-7 shrink-0 text-primary" />
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Détection automatique</h1>
                       
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 sm:w-[360px]">
                    <button
                        type="button"
                        class="rounded-lg border bg-card p-3 text-left shadow-sm transition-colors"
                        :class="!actionLocal ? 'border-gray-900' : 'border-sidebar-border hover:bg-muted/40'"
                        @click="applyFilters('')"
                    >
                        <p class="text-muted-foreground text-xs font-medium">Total</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-gray-900">{{ counts.total }}</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border bg-card p-3 text-left shadow-sm transition-colors"
                        :class="
                            actionLocal === 'a_lier'
                                ? 'border-primary bg-primary/5'
                                : 'border-sidebar-border hover:bg-muted/40'
                        "
                        @click="applyFilters('a_lier')"
                    >
                        <p class="text-xs font-medium text-primary/80">À lier</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-primary">{{ counts.a_lier }}</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border bg-card p-3 text-left shadow-sm transition-colors"
                        :class="
                            actionLocal === 'deja_lie'
                                ? 'border-emerald-600 bg-emerald-50'
                                : 'border-sidebar-border hover:bg-muted/40'
                        "
                        @click="applyFilters('deja_lie')"
                    >
                        <p class="text-xs font-medium text-emerald-700/80">Liées</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-emerald-700">{{ counts.deja_lie }}</p>
                    </button>
                </div>
            </div>

            <div
                v-if="flash?.success"
                class="flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900"
            >
                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"
            >
                {{ flash.error }}
            </div>

            <!-- Filtres : même format (recherche + statut + appliquer) -->
            <div class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-gray-900">
                    <Search class="text-muted-foreground h-4 w-4" />
                    Filtres
                </h2>
                <div class="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                    <div>
                        <Label for="search_det">Recherche</Label>
                        <Input
                            id="search_det"
                            v-model="searchLocal"
                            type="search"
                            class="mt-1.5"
                            placeholder="Staff, matricule, pièce, personne liée…"
                            @keydown.enter.prevent="applyFilters()"
                        />
                    </div>
                    <div>
                        <Label class="mb-1.5 block">Statut d’action</Label>
                        <div class="flex h-9 items-center rounded-md border border-input bg-background p-0.5 shadow-sm">
                            <button
                                type="button"
                                class="h-full rounded px-3 text-xs font-medium transition-colors"
                                :class="
                                    !actionLocal
                                        ? 'bg-muted text-gray-900'
                                        : 'text-muted-foreground hover:text-gray-800'
                                "
                                @click="actionLocal = ''"
                            >
                                Toutes
                            </button>
                            <button
                                type="button"
                                class="h-full rounded px-3 text-xs font-medium transition-colors"
                                :class="
                                    actionLocal === 'a_lier'
                                        ? 'bg-primary/10 text-primary'
                                        : 'text-muted-foreground hover:text-gray-800'
                                "
                                @click="actionLocal = 'a_lier'"
                            >
                                À lier
                            </button>
                            <button
                                type="button"
                                class="h-full rounded px-3 text-xs font-medium transition-colors"
                                :class="
                                    actionLocal === 'deja_lie'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'text-muted-foreground hover:text-gray-800'
                                "
                                @click="actionLocal = 'deja_lie'"
                            >
                                Déjà lié
                            </button>
                        </div>
                    </div>
                    <Button type="button" @click="applyFilters()">Appliquer</Button>
                </div>
            </div>

            <!-- Tableau : mêmes colonnes -->
            <div class="overflow-hidden rounded-lg border border-sidebar-border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-sidebar-border px-4 py-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Liaisons détectées</h2>
                        <p class="text-muted-foreground text-xs">
                            {{ total }} résultat{{ total > 1 ? 's' : '' }}
                            <template v-if="actionLocal === 'a_lier'"> · en attente de liaison</template>
                            <template v-else-if="actionLocal === 'deja_lie'"> · déjà associées</template>
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-left text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="p-3 font-medium">Staff</th>
                                <th class="p-3 font-medium">Contact</th>
                                <th class="p-3 text-right font-medium">Encours staff</th>
                                <th class="p-3 font-medium">Personne liée</th>
                                <th class="p-3 text-right font-medium">Encours lié</th>
                                <th class="p-3 font-medium">Type de relation</th>
                                <th class="p-3 text-right font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="lignes.length === 0">
                                <td colspan="7" class="text-muted-foreground p-10 text-center text-sm">
                                    Aucune liaison pour ces critères.
                                </td>
                            </tr>
                            <tr
                                v-for="(l, idx) in lignes"
                                :key="`${l.matricule_staff}-${l.matricule_personnel_lie}-${idx}`"
                                class="border-b border-gray-100 hover:bg-muted/30"
                            >
                                <td class="p-3">
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-gray-600"
                                        >
                                            {{ initials(l.nom_staff) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ l.nom_staff }}</div>
                                            <div class="text-muted-foreground font-mono text-xs">
                                                {{ l.matricule_staff }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="text-xs text-gray-700">
                                        <span class="font-medium">{{ l.type_piece_staff || '—' }}</span>
                                        <span class="text-muted-foreground font-mono">
                                            · {{ l.numero_piece_staff || '—' }}
                                        </span>
                                    </div>
                                    <div class="text-muted-foreground mt-0.5 text-xs">
                                        {{ l.telephone_staff || '—' }}
                                    </div>
                                </td>
                                <td class="p-3 text-right tabular-nums">{{ formatMontant(l.encours_staff) }}</td>
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ l.nom_personne_liee || '—' }}</div>
                                    <div class="text-muted-foreground font-mono text-xs">
                                        {{ l.matricule_personnel_lie }}
                                    </div>
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ formatMontant(l.encours_personne_liee) }}
                                </td>
                                <td class="p-3">
                                    <span
                                        class="inline-flex rounded-md border px-2 py-0.5 text-xs font-medium"
                                        :class="badgeLiaison(libelleTypeRelation(l))"
                                    >
                                        {{ libelleTypeRelation(l) }}
                                    </span>
                                    <div
                                        v-if="l.detail_liaison"
                                        class="text-muted-foreground mt-1 max-w-[170px] truncate text-xs"
                                        :title="`Compte SI : ${l.detail_liaison}`"
                                    >
                                        Compte {{ l.detail_liaison }}
                                    </div>
                                </td>
                                <td class="p-3 text-right">
                                    <span
                                        v-if="l.deja_lie"
                                        class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800"
                                    >
                                        <BadgeCheck class="h-3.5 w-3.5" />
                                        Déjà lié
                                    </span>
                                    <Button
                                        v-else
                                        type="button"
                                        size="sm"
                                        class="gap-1.5"
                                        @click.stop="ouvrirLier(l)"
                                    >
                                        <Link2 class="h-3.5 w-3.5" />
                                        Lier
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Dialog :open="dialogOpen" @update:open="(v) => (!v ? fermerDialog() : (dialogOpen = v))">
            <DialogContent class="max-w-lg sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Valider la liaison</DialogTitle>
                    <DialogDescription>
                        Vérifiez le détail SI, ajustez si besoin, puis confirmez.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="ligneSelectionnee" class="space-y-4">
                    <div
                        v-if="!ligneSelectionnee.staff_local_id"
                        class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
                    >
                        <UserPlus class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Fiche staff absente : création automatique depuis le SI à la validation.</span>
                    </div>

                    <div class="rounded-lg border border-sidebar-border bg-muted/30 p-4 text-sm">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground text-xs uppercase tracking-wide">Staff</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ligneSelectionnee.nom_staff }}</dd>
                                <dd class="text-muted-foreground font-mono text-xs">
                                    {{ ligneSelectionnee.matricule_staff }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground text-xs uppercase tracking-wide">
                                    Personne liée
                                </dt>
                                <dd class="mt-0.5 font-medium text-gray-900">
                                    {{ ligneSelectionnee.nom_personne_liee || '—' }}
                                </dd>
                                <dd class="text-muted-foreground font-mono text-xs">
                                    {{ ligneSelectionnee.matricule_personnel_lie }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground text-xs uppercase tracking-wide">
                                    Type de liaison SI
                                </dt>
                                <dd class="mt-1">
                                    <span
                                        class="inline-flex rounded-md border px-2 py-0.5 text-xs font-medium"
                                        :class="badgeLiaison(libelleTypeRelation(ligneSelectionnee))"
                                    >
                                        {{ libelleTypeRelation(ligneSelectionnee) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground text-xs uppercase tracking-wide">
                                    Encours personne liée
                                </dt>
                                <dd class="mt-0.5 tabular-nums font-medium">
                                    {{ formatMontant(ligneSelectionnee.encours_personne_liee) }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-muted-foreground text-xs uppercase tracking-wide">
                                    Compte SI
                                </dt>
                                <dd class="mt-1 rounded-md border bg-background px-3 py-2 font-mono text-sm text-gray-900">
                                    {{
                                        ligneSelectionnee.detail_liaison?.trim() || 'Non renseigné'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="validerLiaison">
                        <div class="sm:col-span-2">
                            <Label for="type_relation">Type de relation *</Label>
                            <select
                                id="type_relation"
                                v-model="lierForm.type_relation"
                                required
                                :class="sigTypeRelationSelectClass"
                            >
                                <option value="">— Choisir —</option>
                                <option v-for="t in SIG_TYPES_RELATION" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <InputError :message="lierForm.errors.type_relation" />
                        </div>
                        <div>
                            <Label for="classe">Classe (1–4) *</Label>
                            <select
                                id="classe"
                                v-model.number="lierForm.classe"
                                class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                            >
                                <option :value="1">1</option>
                                <option :value="2">2</option>
                                <option :value="3">3</option>
                                <option :value="4">4</option>
                            </select>
                            <InputError :message="lierForm.errors.classe" />
                        </div>
                        <div>
                            <Label for="complement">Complément (optionnel)</Label>
                            <Input
                                id="complement"
                                v-model="lierForm.complement"
                                class="mt-1.5"
                                placeholder="Précision à ajouter…"
                            />
                        </div>
                    </form>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="secondary" :disabled="lierForm.processing" @click="fermerDialog">
                        Annuler
                    </Button>
                    <Button type="button" class="gap-1.5" :disabled="lierForm.processing" @click="validerLiaison">
                        <Link2 class="h-4 w-4" />
                        {{ lierForm.processing ? 'Liaison…' : 'Valider la liaison' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
