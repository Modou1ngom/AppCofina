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
import { CheckCircle2, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface MissionRow {
    id: number;
    objet: string;
    perimetre?: string;
    nb_chauffeurs_manquants?: number;
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
    { title: 'Validation RH', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Validation RH" />

        <MissionPageShell>
                <MissionCard>
                    <div class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-green-50 p-2.5 text-green-600">
                                <CheckCircle2 class="h-6 w-6 shrink-0" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold sm:text-2xl">Validation RH</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Consultez l'aperçu de l'ordre de mission, puis générez les ordres pour transmission au Responsable RH.
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
                        <table class="w-full min-w-[720px] text-sm">
                    <thead :class="missionTableHead">
                        <tr>
                            <th :class="missionTableTh">N°</th>
                            <th :class="missionTableTh">Objet</th>
                            <th :class="missionTableTh">Destination</th>
                            <th :class="missionTableTh">Chauffeurs</th>
                            <th :class="missionTableTh">Statut</th>
                            <th :class="missionTableTh">Étape</th>
                            <th :class="missionTableThRight">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in missions.data" :key="m.id" :class="missionTableRow">
                            <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>
                            <td class="p-3">
                                <Link :href="`/missions/${m.id}`" class="font-medium text-blue-700 hover:underline">
                                    {{ m.objet }}
                                </Link>
                            </td>
                            <td class="p-3">{{ m.perimetre || '—' }}</td>
                            <td class="p-3">
                                <span v-if="(m.nb_chauffeurs_manquants ?? 0) > 0" class="text-amber-800">
                                    {{ m.nb_chauffeurs_manquants }} manquant(s)
                                </span>
                                <span v-else class="text-green-700">OK</span>
                            </td>
                            <td class="p-3">
                                <MissionValidationEtatBadge :etat="m.validation_etat" />
                            </td>
                            <td class="p-3 text-muted-foreground">{{ m.etape_libelle || '—' }}</td>
                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a :href="`/missions/${m.id}/ordre-preview`" target="_blank" rel="noopener noreferrer">
                                        <Button size="sm" variant="outline" class="gap-2" type="button">
                                            <Printer class="h-4 w-4" /> Aperçu / Imprimer
                                        </Button>
                                    </a>
                                    <Link :href="`/missions/${m.id}`">
                                        <Button
                                            size="sm"
                                            class="gap-2"
                                            :class="m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee'
                                                ? 'bg-green-600 text-white hover:bg-green-700'
                                                : ''"
                                            :variant="m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee' ? 'default' : 'outline'"
                                        >
                                            <CheckCircle2 class="h-4 w-4" />
                                            {{ m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee' ? 'Traiter' : 'Consulter' }}
                                        </Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!missions.data.length">
                            <td colspan="7" :class="missionEmptyState">
                                Aucune mission pour le moment. Les dossiers apparaissent ici après validation Facilities (étape RH).
                            </td>
                        </tr>
                    </tbody>
                        </table>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
