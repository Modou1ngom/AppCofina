<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { formatPeriodeMission } from '@/lib/utils';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import MissionNumeroSearch from '@/components/missions/MissionNumeroSearch.vue';
import { formatNumeroMission } from '@/lib/utils';
import { missionTableWrap, missionTableHead, missionTableRow, missionEmptyState, missionTableTh, missionTableThRight, missionNumeroCell } from '@/lib/missionPastel';
import { Eye, FileText } from 'lucide-vue-next';

interface MissionRow {
    id: number;
    objet: string;
    current_step: string;
    date_debut: string;
    date_fin: string;
    rapport_signataire_nom?: string | null;
    rapport_soumis_at?: string | null;
    rapport_valide_at?: string | null;
    demandeur?: { name: string };
    beneficiaire?: { name: string };
}

interface Props {
    missions: { data: MissionRow[] };
    filtreNumero?: string;
}

withDefaults(defineProps<Props>(), {
    filtreNumero: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Rapport de mission', href: '#' },
];

const libelleEtape = (step: string) => ({
    ATTENTE_RAPPORT: 'À rédiger (missionnaire)',
    ATTENTE_VALIDATION_RAPPORT: 'À traiter (demandeur)',
    CLOTUREE: 'Clôturée',
}[step] ?? step);

const badgeClass = (step: string) => ({
    ATTENTE_RAPPORT: 'bg-amber-50 text-amber-800 border-amber-200',
    ATTENTE_VALIDATION_RAPPORT: 'bg-blue-50 text-blue-800 border-blue-200',
    CLOTUREE: 'bg-emerald-50 text-emerald-800 border-emerald-200',
}[step] ?? 'bg-slate-50 text-slate-700');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Rapport de mission" />

        <MissionPageShell>
                <MissionCard>
                    <div class="flex flex-col gap-4 border-b pb-5">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-indigo-50 p-2.5 text-indigo-600">
                                <FileText class="h-6 w-6 shrink-0" />
                            </div>
                            <div>
                                <h1 class="text-xl font-semibold sm:text-2xl">Rapport de mission</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Soumettez votre rapport signé après validation, ou validez le rapport en tant que demandeur.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div :class="[missionTableWrap, 'overflow-hidden']">
                        <MissionNumeroSearch :filtre-numero="filtreNumero" />
                        <table class="w-full min-w-[720px] text-sm">
                            <thead :class="missionTableHead">
                                <tr>
                                    <th :class="missionTableTh">N°</th>
                                    <th :class="missionTableTh">Objet</th>
                                    <th :class="missionTableTh">Période</th>
                                    <th :class="missionTableTh">Demandeur</th>
                                    <th :class="missionTableTh">Étape</th>
                                    <th :class="missionTableTh">Signataire</th>
                                    <th :class="missionTableThRight">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in missions.data" :key="m.id" :class="missionTableRow">
                                    <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>
                                    <td class="p-3 font-medium">{{ m.objet }}</td>
                                    <td class="p-3 tabular-nums">{{ formatPeriodeMission(m.date_debut, m.date_fin) }}</td>
                                    <td class="p-3">{{ m.demandeur?.name ?? '—' }}</td>
                                    <td class="p-3">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                            :class="badgeClass(m.current_step)"
                                        >
                                            {{ libelleEtape(m.current_step) }}
                                        </span>
                                    </td>
                                    <td class="p-3">{{ m.rapport_signataire_nom ?? '—' }}</td>
                                    <td class="p-3 text-right">
                                        <Link :href="`/missions/${m.id}`">
                                            <Button size="sm" variant="outline" class="gap-2">
                                                <Eye class="h-4 w-4" /> Ouvrir
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!missions.data.length">
                                    <td colspan="7" :class="missionEmptyState">
                                        Aucun rapport de mission en cours.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
