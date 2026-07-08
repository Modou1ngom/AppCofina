<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { formatPeriodeMission, formatNumeroMission } from '@/lib/utils';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import MissionMissionnaireTabs from '@/components/missions/MissionMissionnaireTabs.vue';
import MissionNumeroSearch from '@/components/missions/MissionNumeroSearch.vue';
import { missionListItem, missionEmptyState, missionTableWrap } from '@/lib/missionPastel';
import { Eye, MapPin, UserRound, FileText, PenLine } from 'lucide-vue-next';

interface MissionRow {
    id: number;
    numero_mission?: number | null;
    objet: string;
    current_step: string;
    status: string;
    date_debut: string;
    date_fin: string;
    perimetre?: string | null;
    sites_affichage?: string[];
    etape_libelle?: string;
    peut_soumettre_rapport?: boolean;
    est_demandeur?: boolean;
    rapport_soumis_at?: string | null;
    demandeur?: { name: string };
}

interface Props {
    missions: { data: MissionRow[] };
    activeTab?: 'rapport' | 'dashboard';
    filtreNumero?: string;
}

withDefaults(defineProps<Props>(), {
    activeTab: 'dashboard',
    filtreNumero: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Rapport de mission', href: '/missions/rapports' },
    { title: 'Dashboard missionnaire', href: '#' },
];

const libelleEtape = (step: string, libelle?: string) => libelle ?? step;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Dashboard missionnaire" />

        <MissionPageShell>
                <MissionCard>
                    <div class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-indigo-50 p-2.5 text-indigo-600">
                                <UserRound class="h-6 w-6 shrink-0" />
                            </div>
                            <div>
                                <h1 class="text-xl font-semibold sm:text-2xl">Dashboard missionnaire</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Consultez vos missions : sites, dates, avancement et actions disponibles (rapport, ordre de mission).
                                </p>
                            </div>
                        </div>
                        <MissionMissionnaireTabs :active-tab="activeTab" />
                    </div>

                    <div :class="[missionTableWrap, 'overflow-hidden']">
                        <MissionNumeroSearch :filtre-numero="filtreNumero" />
                        <div class="space-y-4 p-4">
                        <div
                            v-for="m in missions.data"
                            :key="m.id"
                            :class="missionListItem"
                        >
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-mono font-semibold text-slate-500 tabular-nums">N° {{ formatNumeroMission(m.numero_mission) }}</p>
                                    <h2 class="font-semibold text-slate-900">{{ m.objet }}</h2>
                                    <p class="text-sm text-muted-foreground mt-0.5">
                                        Demandeur : {{ m.demandeur?.name ?? '—' }}
                                        <span v-if="m.est_demandeur" class="text-indigo-600"> (vous)</span>
                                    </p>
                                </div>
                                <span class="inline-flex rounded-full border border-rose-200/80 bg-rose-50/80 px-2.5 py-0.5 text-xs font-medium text-rose-800">
                                    {{ libelleEtape(m.current_step, m.etape_libelle) }}
                                </span>
                            </div>

                            <div class="grid gap-2 text-sm sm:grid-cols-2">
                                <p><strong>Période :</strong> {{ formatPeriodeMission(m.date_debut, m.date_fin) }}</p>
                                <p class="flex items-start gap-1">
                                    <MapPin class="h-4 w-4 shrink-0 mt-0.5 text-slate-500" />
                                    <span>
                                        <strong>Sites :</strong>
                                        {{ (m.sites_affichage?.length ? m.sites_affichage.join(', ') : m.perimetre) || '—' }}
                                    </span>
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 pt-1">
                                <Link :href="`/missions/${m.id}`">
                                    <Button size="sm" variant="outline" class="gap-2">
                                        <Eye class="h-4 w-4" /> Voir la fiche
                                    </Button>
                                </Link>
                                <Link v-if="m.peut_soumettre_rapport" :href="`/missions/${m.id}`">
                                    <Button size="sm" class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700">
                                        <PenLine class="h-4 w-4" /> Soumettre le rapport
                                    </Button>
                                </Link>
                                <Link v-else-if="m.rapport_soumis_at" :href="`/missions/${m.id}`">
                                    <Button size="sm" variant="outline" class="gap-2">
                                        <FileText class="h-4 w-4" /> Rapport soumis
                                    </Button>
                                </Link>
                            </div>
                        </div>

                        <p v-if="!missions.data.length" :class="[missionEmptyState, '!rounded-none !border-0']">
                            Vous n'êtes missionnaire sur aucune mission pour le moment.
                        </p>
                        </div>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
