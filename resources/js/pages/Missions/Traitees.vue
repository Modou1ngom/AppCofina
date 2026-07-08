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
import MissionTraiteesTabs from '@/components/missions/MissionTraiteesTabs.vue';
import { Archive, Eye } from 'lucide-vue-next';
import { computed } from 'vue';

interface MissionRow {
    id: number;
    objet: string;
    perimetre?: string;
    current_step?: string;
    status?: string;
    validation_etat?: string;
    etape_libelle?: string;
    demandeur?: { name: string };
}

interface Props {
    missions: { data: MissionRow[] };
    activeTab?: 'liste' | 'recap';
    filtreNumero?: string;
}

withDefaults(defineProps<Props>(), {
    activeTab: 'liste',
    filtreNumero: '',
});

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Traitées/Cloturées', href: '#' },
];
const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        brouillon: 'Brouillon',
        en_cours: 'En cours',
        renvoye: 'Renvoyée',
        valide: 'Validée',
        rejete: 'Rejetée',
        validee: 'Validée',
        rejetee: 'Rejetée',
        cloture: 'Clôturée',
    };
    return labels[status] || status;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Traitées/Cloturées" />

        <MissionPageShell>
                <MissionCard>
                    <div class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-slate-100 p-2.5 text-slate-700">
                                <Archive class="h-6 w-6 shrink-0" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold sm:text-2xl">Traitées/Cloturées</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Missions que vous avez déjà validées ou qui sont clôturées. Si une mission vous est renvoyée, elle réapparaît dans l'onglet de validation correspondant.
                                </p>
                            </div>
                        </div>
                        <MissionTraiteesTabs :active-tab="activeTab" />
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
                                    <th :class="missionTableTh">Demandeur</th>
                                    <th :class="missionTableTh">Étape</th>
                                    <th :class="missionTableTh">État</th>
                                    <th :class="missionTableTh">Statut</th>
                                    <th :class="missionTableThRight">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in missions.data" :key="m.id" :class="missionTableRow">
                                    <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>
                                    <td class="p-3 font-medium">{{ m.objet }}</td>
                                    <td class="p-3">{{ m.perimetre || '—' }}</td>
                                    <td class="p-3">{{ m.demandeur?.name || '—' }}</td>
                                    <td class="p-3 text-muted-foreground">{{ m.etape_libelle || '—' }}</td>
                                    <td class="p-3">
                                        <MissionValidationEtatBadge :etat="m.validation_etat" />
                                    </td>
                                    <td class="p-3 text-muted-foreground">{{ getStatusLabel(m.status ?? '') }}</td>
                                    <td class="p-3 text-right">
                                        <Link :href="`/missions/${m.id}`">
                                            <Button size="sm" variant="outline" class="gap-2">
                                                <Eye class="h-4 w-4" />
                                                Consulter
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!missions.data.length">
                                    <td colspan="8" :class="missionEmptyState">
                                        Aucune mission traitée ou clôturée pour le moment.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
