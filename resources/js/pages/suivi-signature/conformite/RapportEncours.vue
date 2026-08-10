<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FileDown, Shield } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface StaffMini {
    id: number;
    reference: string;
    prenom: string;
    nom: string;
    numero_client_si: string | null;
}

interface StaffDepasse {
    id: number;
    reference: string;
    prenom: string;
    nom: string;
    fonds_propres: string | null;
    encours_credit_individuel: string;
}

interface EventRow {
    id: number;
    type: string;
    fonds_propres: string | null;
    encours_consolide: string;
    taux_pct: string | null;
    seuil_pct: string;
    commentaire: string | null;
    created_at: string;
    staff: StaffMini | null;
    user: { name: string; email: string } | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface TypeOption {
    value: string;
    label: string;
}

interface Props {
    events: Paginated<EventRow>;
    fichesEnDepassement: StaffDepasse[];
    staffPourFiltre: StaffMini[];
    staffFilterHasHistory?: boolean | null;
    filters: { du: string; au: string; type: string; staff_id: number | null };
    seuilTauxPct: number;
    typeOptions: TypeOption[];
}

const props = defineProps<Props>();

const du = ref(props.filters.du || '');
const au = ref(props.filters.au || '');
const type = ref(props.filters.type || '');
const staffId = ref(props.filters.staff_id != null ? String(props.filters.staff_id) : '');

watch(
    () => props.filters,
    (f) => {
        du.value = f.du || '';
        au.value = f.au || '';
        type.value = f.type || '';
        staffId.value = f.staff_id != null ? String(f.staff_id) : '';
    },
    { deep: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suivi signature', href: '/suivi-signature/staff' },
    { title: 'Rapport conformité encours', href: '#' },
];

function formatDt(iso: string): string {
    try {
        return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function libelleStaffFiltre(s: StaffMini): string {
    let t = `${s.reference} — ${s.prenom} ${s.nom}`;
    if (s.numero_client_si) {
        t += ` · n° client SI ${s.numero_client_si}`;
    }
    return t;
}

function formatMontant(n: string | number | null | undefined): string {
    if (n === null || n === undefined || n === '') return '—';
    const x = typeof n === 'number' ? n : parseFloat(String(n).replace(',', '.'));
    if (Number.isNaN(x)) return String(n);
    return x.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilters() {
    const p = new URLSearchParams();
    if (du.value) p.set('du', du.value);
    if (au.value) p.set('au', au.value);
    if (type.value) p.set('type', type.value);
    if (staffId.value) p.set('staff_id', staffId.value);
    router.get(`/suivi-signature/conformite/rapport-encours?${p.toString()}`, {}, { preserveState: true });
}

function exportCsvUrl(): string {
    const p = new URLSearchParams();
    if (du.value) p.set('du', du.value);
    if (au.value) p.set('au', au.value);
    if (type.value) p.set('type', type.value);
    if (staffId.value) p.set('staff_id', staffId.value);
    const q = p.toString();
    return `/suivi-signature/conformite/rapport-encours/export${q ? `?${q}` : ''}`;
}

const typeLabel = computed(() => {
    const m: Record<string, string> = {};
    props.typeOptions.forEach((o) => {
        if (o.value) m[o.value] = o.label;
    });
    return (t: string) => m[t] ?? t;
});

const messageListeVide = computed(() => {
    if (props.events.data.length > 0) {
        return '';
    }
    const staffId = props.filters.staff_id;
    const hist = props.staffFilterHasHistory;

    if (staffId != null && hist === true) {
        return 'Aucun événement sur cette période pour cette fiche. Élargissez ou videz les dates « Du » / « Au », ou choisissez « Toutes » les fiches pour voir l’historique complet.';
    }
    if (staffId != null && hist === false) {
        return 'Aucun historique enregistré pour cette fiche : une ligne est créée lors d’un dépassement du seuil, d’un retour sous le seuil, ou lorsque vous ajoutez un commentaire depuis la fiche staff. Ouvrez la fiche pour enregistrer une justification, ou attendez le prochain recalcul des encours (visite fiche, liaison, etc.).';
    }

    return 'Aucun événement pour ces critères. Les lignes sont créées automatiquement aux franchissements du seuil ; vous pouvez aussi consigner un commentaire sur chaque fiche staff (conformité).';
});
</script>

<template>
    <Head title="Rapport conformité — encours / fonds propres" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <Shield class="h-7 w-7 text-amber-700" />
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Rapport de conformité</h1>
                       
                    </div>
                </div>
                <a :href="exportCsvUrl()">
                    <Button type="button" variant="outline" class="gap-2">
                        <FileDown class="h-4 w-4" />
                        Export CSV
                    </Button>
                </a>
            </div>

            <div
                v-if="fichesEnDepassement.length > 0"
                class="rounded-lg border border-red-200 bg-red-50/80 p-4 text-sm text-red-950"
            >
                <p class="font-semibold">Fiches actuellement au-dessus du seuil ({{ fichesEnDepassement.length }})</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li v-for="s in fichesEnDepassement" :key="s.id">
                        <Link :href="`/suivi-signature/staff/${s.id}`" class="font-medium underline">
                            {{ s.reference }} — {{ s.prenom }} {{ s.nom }}
                        </Link>
                        <span class="text-muted-foreground">
                            (encours total {{ formatMontant(s.encours_credit_individuel) }} ; FP réf.
                            {{ formatMontant(s.fonds_propres) }})
                        </span>
                    </li>
                </ul>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card p-4">
                <h2 class="mb-3 text-base font-semibold">Filtres</h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <Label for="du">Du</Label>
                        <Input id="du" v-model="du" type="date" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="au">Au</Label>
                        <Input id="au" v-model="au" type="date" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="type_ev">Type d’événement</Label>
                        <select
                            id="type_ev"
                            v-model="type"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option v-for="o in typeOptions" :key="o.value || 'all'" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <Label for="staff_f">Fiche staff</Label>
                        <select
                            id="staff_f"
                            v-model="staffId"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="">Toutes</option>
                            <option v-for="s in staffPourFiltre" :key="s.id" :value="String(s.id)">
                                {{ libelleStaffFiltre(s) }}
                            </option>
                        </select>
                    </div>
                </div>
                <Button type="button" class="mt-4" @click="applyFilters">Appliquer</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-sidebar-border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="p-3 font-medium">Date</th>
                            <th class="p-3 font-medium">Type</th>
                            <th class="p-3 font-medium">Staff</th>
                            <th class="p-3 font-medium tabular-nums">FP</th>
                            <th class="p-3 font-medium tabular-nums">Encours cons.</th>
                            <th class="p-3 font-medium tabular-nums">Taux %</th>
                            <th class="p-3 font-medium">Commentaire / auteur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ev in events.data" :key="ev.id" class="border-b border-gray-100">
                            <td class="p-3 whitespace-nowrap">{{ formatDt(ev.created_at) }}</td>
                            <td class="p-3">{{ typeLabel(ev.type) }}</td>
                            <td class="p-3">
                                <Link
                                    v-if="ev.staff"
                                    :href="`/suivi-signature/staff/${ev.staff.id}`"
                                    class="text-primary font-medium hover:underline"
                                >
                                    {{ ev.staff.reference }}
                                </Link>
                                <span v-else>—</span>
                            </td>
                            <td class="p-3 tabular-nums">{{ formatMontant(ev.fonds_propres) }}</td>
                            <td class="p-3 tabular-nums">{{ formatMontant(ev.encours_consolide) }}</td>
                            <td class="p-3 tabular-nums">
                                {{ ev.taux_pct != null ? `${formatMontant(ev.taux_pct)} %` : '—' }}
                            </td>
                            <td class="p-3 max-w-md">
                                <p v-if="ev.commentaire" class="whitespace-pre-wrap text-xs">{{ ev.commentaire }}</p>
                                <p v-if="ev.user" class="text-muted-foreground mt-1 text-xs">{{ ev.user.name || ev.user.email }}</p>
                            </td>
                        </tr>
                        <tr v-if="events.data.length === 0">
                            <td colspan="7" class="text-muted-foreground p-8 text-center text-sm leading-relaxed">
                                {{ messageListeVide }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="events.last_page > 1" class="flex flex-wrap items-center justify-center gap-2">
                <template v-for="(l, i) in events.links" :key="i">
                    <Link
                        v-if="l.url"
                        :href="l.url"
                        class="rounded-md border px-3 py-1 text-sm"
                        :class="l.active ? 'border-primary bg-primary/10 font-semibold' : 'border-gray-200 hover:bg-muted'"
                        preserve-state
                    >
                        <span v-html="l.label" />
                    </Link>
                    <span v-else class="text-muted-foreground px-2 text-sm" v-html="l.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
