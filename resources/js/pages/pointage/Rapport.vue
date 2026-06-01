<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import {
    CalendarClock,
    MapPin,
    Users,
    ClipboardList,
    Download,
    ArrowRight,
} from 'lucide-vue-next';

interface Stats {
    pointages_aujourdhui: number;
    sites_actifs: number;
    declarations_pending_manager: number;
    declarations_pending_rh: number;
    collaborateurs_pointes_7j: number;
}

interface RecentRow {
    id: number;
    sens: string;
    type: string;
    enregistre_at: string;
    source: string;
    user_name: string | null;
    user_email: string | null;
    site_nom: string | null;
}

interface Props {
    stats: Stats;
    recentPointages: RecentRow[];
}

defineProps<Props>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage' },
    { title: 'Tableau de bord', href: '#' },
];

const sensLabel = (s: string) => (s === 'entree' ? 'Entrée' : 'Sortie');

const typeLabel = (t: string) =>
    ({
        checkin: 'Check-in',
        checkout: 'Check-out',
    } as Record<string, string>)[t] || t;

const sourceLabel = (s: string) =>
    ({
        mobile: 'Appli',
        navigateur: 'Navigateur',
        manuel: 'Manuel',
        scan: 'Scan',
    } as Record<string, string>)[s] || s;

const exportQuotidien = '/pointage/rapport/export-quotidien';
const exportJournalier = '/pointage/rapport/export-journalier-rh';
const exportSynthese = '/pointage/rapport/export-synthese-rh';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pointage — Tableau de bord" />

        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <CalendarClock class="h-7 w-7 text-primary" />
                        Pointage — tableau de bord
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Vue d’ensemble pour l’administration et les RH : volumes du jour, files de validation et exports.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <a :href="exportQuotidien" target="_blank" rel="noopener noreferrer">
                            <Download class="mr-2 h-4 w-4" />
                            Export du jour
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="exportJournalier" target="_blank" rel="noopener noreferrer">
                            <Download class="mr-2 h-4 w-4" />
                            Journalier (30 j.)
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="exportSynthese" target="_blank" rel="noopener noreferrer">
                            <Download class="mr-2 h-4 w-4" />
                            Synthèse
                        </a>
                    </Button>
                </div>
            </div>

            <div v-if="flash?.success" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                {{ flash.error }}
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pointages aujourd’hui</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ stats.pointages_aujourdhui }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Sites actifs</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ stats.sites_actifs }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Décl. manager</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ stats.declarations_pending_manager }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Décl. RH</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ stats.declarations_pending_rh }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Collab. (7 j.)</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ stats.collaborateurs_pointes_7j }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Link
                    href="/pointage/sites"
                    class="group flex items-center justify-between rounded-xl border bg-card p-5 shadow-sm transition hover:border-primary/40"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-primary/10 p-3 text-primary">
                            <MapPin class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="font-semibold">Sites de pointage</p>
                            <p class="text-sm text-muted-foreground">Créer, activer ou régénérer les codes</p>
                        </div>
                    </div>
                    <ArrowRight class="h-5 w-5 text-muted-foreground transition group-hover:translate-x-0.5" />
                </Link>
                <Link
                    href="/pointage/declarations/validation-rh"
                    class="group flex items-center justify-between rounded-xl border bg-card p-5 shadow-sm transition hover:border-primary/40"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-primary/10 p-3 text-primary">
                            <ClipboardList class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="font-semibold">Validation RH</p>
                            <p class="text-sm text-muted-foreground">{{ stats.declarations_pending_rh }} en attente</p>
                        </div>
                    </div>
                    <ArrowRight class="h-5 w-5 text-muted-foreground transition group-hover:translate-x-0.5" />
                </Link>
                <Link
                    href="/pointage/declarations/validation-manager"
                    class="group flex items-center justify-between rounded-xl border bg-card p-5 shadow-sm transition hover:border-primary/40"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-primary/10 p-3 text-primary">
                            <Users class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="font-semibold">Validation managers</p>
                            <p class="text-sm text-muted-foreground">{{ stats.declarations_pending_manager }} en attente (tous managers)</p>
                        </div>
                    </div>
                    <ArrowRight class="h-5 w-5 text-muted-foreground transition group-hover:translate-x-0.5" />
                </Link>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b px-6 py-4">
                    <h2 class="font-semibold">Derniers pointages</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-medium uppercase text-muted-foreground">
                            <tr>
                                <th class="p-3">Date / heure</th>
                                <th class="p-3">Collaborateur</th>
                                <th class="p-3">Site</th>
                                <th class="p-3">Sens</th>
                                <th class="p-3">Type API</th>
                                <th class="p-3">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in recentPointages" :key="r.id" class="border-t">
                                <td class="p-3 tabular-nums text-muted-foreground">
                                    {{ new Date(r.enregistre_at).toLocaleString('fr-FR') }}
                                </td>
                                <td class="p-3">
                                    <span class="font-medium">{{ r.user_name ?? '—' }}</span>
                                    <span v-if="r.user_email" class="block text-xs text-muted-foreground">{{ r.user_email }}</span>
                                </td>
                                <td class="p-3">{{ r.site_nom ?? '—' }}</td>
                                <td class="p-3">{{ sensLabel(r.sens) }}</td>
                                <td class="p-3 text-muted-foreground">{{ typeLabel(r.type) }}</td>
                                <td class="p-3 text-muted-foreground">{{ sourceLabel(r.source) }}</td>
                            </tr>
                            <tr v-if="!recentPointages.length">
                                <td colspan="6" class="p-8 text-center text-muted-foreground">Aucun pointage enregistré.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
