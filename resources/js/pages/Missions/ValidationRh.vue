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
import { Eye, Users } from 'lucide-vue-next';

interface MissionRow {
    id: number;
    objet: string;
    priorite: string;
    date_debut: string;
    date_fin: string;
    demandeur?: { name: string };
    participants?: { name: string }[];
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
    { title: 'Intervention RH', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Intervention RH — Missions" />

        <MissionPageShell>
            <MissionCard>
            <div class="flex items-center gap-3 border-b pb-5">
                <Users class="h-6 w-6 text-blue-600" />
                <div>
                    <h1 class="text-2xl font-semibold">Niveau 3 — Intervention RH</h1>
                    <p class="text-sm text-muted-foreground">Analysez les besoins logistiques et transmettez à Facilities.</p>
                </div>
            </div>

            <div :class="[missionTableWrap, 'overflow-hidden']">
                <MissionNumeroSearch :filtre-numero="filtreNumero" />
                <table class="w-full text-sm">
                    <thead :class="missionTableHead">
                        <tr>
                            <th :class="missionTableTh">N°</th>
                            <th :class="missionTableTh">Objet</th>
                            <th :class="missionTableTh">Demandeur</th>
                            <th :class="missionTableTh">Dates</th>
                            <th :class="missionTableThRight">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in missions.data" :key="m.id" :class="missionTableRow">
                            <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>
                            <td class="p-3">{{ m.objet }}</td>
                            <td class="p-3">{{ m.demandeur?.name }}</td>
                            <td class="p-3">{{ formatPeriodeMission(m.date_debut, m.date_fin) }}</td>
                            <td class="p-3 text-right">
                                <Link :href="`/missions/${m.id}`">
                                    <Button size="sm" variant="outline" class="gap-2">
                                        <Eye class="h-4 w-4" /> Traiter
                                    </Button>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!missions.data.length">
                            <td colspan="5" :class="missionEmptyState">Aucune mission en attente RH.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
