<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MissionLogistiqueTabs from '@/components/missions/MissionLogistiqueTabs.vue';
import MissionValidationEtatBadge from '@/components/missions/MissionValidationEtatBadge.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import MissionNumeroSearch from '@/components/missions/MissionNumeroSearch.vue';
import { formatNumeroMission } from '@/lib/utils';
import { missionTableWrap, missionTableHead, missionTableRow, missionEmptyState, missionTableTh, missionTableThRight, missionNumeroCell } from '@/lib/missionPastel';
import { Eye, Truck } from 'lucide-vue-next';

interface MissionRow {
    id: number;
    objet: string;
    perimetre?: string;
    demandeur?: { name: string };
    missionnaires_count?: number;
    validation_etat?: string;
    etape_libelle?: string;
}

interface Props {
    missions: { data: MissionRow[] };
    activeTab?: 'file' | 'recap';
    filtreNumero?: string;
}

withDefaults(defineProps<Props>(), {
    activeTab: 'file',
    filtreNumero: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Facilities', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Facilities — Missions" />

        <MissionPageShell>
                <MissionCard>
                    <div class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-amber-50 p-2.5 text-amber-600">
                                <Truck class="h-6 w-6 shrink-0" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold sm:text-2xl">Facilities — Analyse logistique</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Renseignez la logistique et les coûts par missionnaire, puis transmettez à la RH.
                                </p>
                            </div>
                        </div>
                        <MissionLogistiqueTabs context="facilities" :active-tab="activeTab" />
                    </div>

                    <div :class="[missionTableWrap, 'overflow-hidden']">
                        <MissionNumeroSearch :filtre-numero="filtreNumero" />
                        <table class="w-full min-w-[640px] text-sm">
                    <thead :class="missionTableHead">
                        <tr>
                            <th :class="missionTableTh">N°</th>
                            <th :class="missionTableTh">Objet</th>
                            <th :class="missionTableTh">Destination</th>
                            <th :class="missionTableTh">Missionnaires</th>
                            <th :class="missionTableTh">Statut</th>
                            <th :class="missionTableTh">Étape</th>
                            <th :class="missionTableThRight">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in missions.data" :key="m.id" :class="missionTableRow">
                            <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>
                            <td class="p-3">{{ m.objet }}</td>
                            <td class="p-3">{{ m.perimetre || '—' }}</td>
                            <td class="p-3">{{ m.missionnaires_count ?? 0 }}</td>
                            <td class="p-3">
                                <MissionValidationEtatBadge :etat="m.validation_etat" />
                            </td>
                            <td class="p-3 text-muted-foreground">{{ m.etape_libelle || '—' }}</td>
                            <td class="p-3 text-right">
                                <Link :href="`/missions/validation/facilities/${m.id}`">
                                    <Button size="sm" variant="outline" class="gap-2">
                                        <Eye class="h-4 w-4" />
                                        {{ m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee' ? 'Traiter' : 'Consulter' }}
                                    </Button>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!missions.data.length">
                            <td colspan="7" :class="missionEmptyState">Aucune mission en attente Facilities.</td>
                        </tr>
                    </tbody>
                        </table>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
