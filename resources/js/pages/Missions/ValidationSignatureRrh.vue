<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MissionValidationEtatBadge from '@/components/missions/MissionValidationEtatBadge.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import MissionNumeroSearch from '@/components/missions/MissionNumeroSearch.vue';
import { formatNumeroMission } from '@/lib/utils';
import { missionTableWrap, missionTableHead, missionTableRow, missionEmptyState, missionTableTh, missionTableThRight, missionNumeroCell } from '@/lib/missionPastel';
import { PenLine, Stamp } from 'lucide-vue-next';
import { computed } from 'vue';

interface MissionRow {
    id: number;
    objet: string;
    perimetre?: string;
    validation_etat?: string;
    etape_libelle?: string;
    demandeur?: { name: string };
}

interface Props {
    missions: { data: MissionRow[] };
    filtreNumero?: string;
}

withDefaults(defineProps<Props>(), {
    filtreNumero: '',
});

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Signature Responsable RH', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Signature Responsable RH" />

        <MissionPageShell>
                <MissionCard>
                    <div class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-rose-50 p-2.5 text-rose-700">
                                <Stamp class="h-6 w-6 shrink-0" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold sm:text-2xl">Signature Responsable RH</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Signez électroniquement les ordres générés par la RH.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="flash?.success" class="mt-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                        {{ flash.success }}
                    </div>
                    <div v-if="flash?.error" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        {{ flash.error }}
                    </div>

                    <div :class="[missionTableWrap, '!mt-5 overflow-hidden']">
                        <MissionNumeroSearch :filtre-numero="filtreNumero" />
                        <table class="w-full min-w-[640px] text-sm">
                            <thead :class="missionTableHead">
                                <tr>
                                    <th :class="missionTableTh">N°</th>
                                    <th :class="missionTableTh">Objet</th>
                                    <th :class="missionTableTh">Destination</th>
                                    <th :class="missionTableTh">Demandeur</th>
                                    <th :class="missionTableTh">Statut</th>
                                    <th :class="missionTableTh">Étape</th>
                                    <th :class="missionTableThRight">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!missions.data.length">
                                    <td colspan="7" :class="missionEmptyState">
                                        Aucun ordre de mission pour le moment.
                                    </td>
                                </tr>
                                <tr v-for="m in missions.data" :key="m.id" :class="missionTableRow">
                                    <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>
                                    <td class="p-3">
                                        <Link :href="`/missions/${m.id}`" class="font-medium text-rose-700 hover:underline">
                                            {{ m.objet }}
                                        </Link>
                                    </td>
                                    <td class="p-3">{{ m.perimetre || '—' }}</td>
                                    <td class="p-3">{{ m.demandeur?.name || '—' }}</td>
                                    <td class="p-3">
                                        <MissionValidationEtatBadge :etat="m.validation_etat" />
                                    </td>
                                    <td class="p-3 text-muted-foreground">{{ m.etape_libelle || '—' }}</td>
                                    <td class="p-3 text-right">
                                        <Link :href="`/missions/${m.id}`">
                                            <Button
                                                size="sm"
                                                class="gap-2"
                                                :class="m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee'
                                                    ? 'bg-rose-700 text-white hover:bg-rose-800'
                                                    : ''"
                                                :variant="m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee' ? 'default' : 'outline'"
                                            >
                                                <PenLine class="h-4 w-4" />
                                                {{ m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee' ? 'Signer' : 'Consulter' }}
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
