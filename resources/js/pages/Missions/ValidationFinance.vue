<script setup lang="ts">

import { Head, Link, usePage } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';

import MissionLogistiqueTabs from '@/components/missions/MissionLogistiqueTabs.vue';

import MissionNumeroSearch from '@/components/missions/MissionNumeroSearch.vue';

import MissionValidationEtatBadge from '@/components/missions/MissionValidationEtatBadge.vue';

import { type BreadcrumbItem } from '@/types';

import { Button } from '@/components/ui/button';

import { formatMontant, formatPeriodeMission, formatNumeroMission } from '@/lib/utils';

import MissionPageShell from '@/components/missions/MissionPageShell.vue';

import MissionCard from '@/components/missions/MissionCard.vue';

import {

    missionTableWrap,

    missionTableHead,

    missionTableRow,

    missionEmptyState,

    missionTableTh,

    missionTableThRight,

    missionNumeroCell,

} from '@/lib/missionPastel';

import { Eye, DollarSign, CheckCircle2 } from 'lucide-vue-next';

import { computed } from 'vue';



interface MissionRow {

    id: number;

    objet: string;

    total_logistique?: number | null;

    priorite: string;

    date_debut: string;

    date_fin: string;

    demandeur?: { name: string };

    beneficiaire?: { name: string };

    status: string;

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



const page = usePage();

const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);



const breadcrumbs: BreadcrumbItem[] = [

    { title: 'Gestion des missions', href: '/missions' },

    { title: 'Validation Finance', href: '#' },

];



const priorityClass = (p: string) => {

    switch (p) {

        case 'critique':

            return 'text-red-600 font-bold';

        case 'urgente':

            return 'text-amber-600 font-semibold';

        default:

            return 'text-slate-600';

    }

};

</script>



<template>

    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Validation Finance — Gestion des missions" />



        <MissionPageShell>

                <MissionCard class="space-y-6">

                    <div class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-start lg:justify-between">

                        <div class="flex items-start gap-3">

                            <div class="rounded-full bg-green-50 p-2.5 text-green-600">

                                <DollarSign class="h-6 w-6 shrink-0" />

                            </div>

                            <div>

                                <h1 class="text-xl font-semibold sm:text-2xl">Validation Finance</h1>

                                <p class="mt-1 text-sm text-muted-foreground">

                                    Validez les dépenses logistiques après saisie Facilities. Le récapitulatif est mis à jour à chaque traitement.

                                </p>

                            </div>

                        </div>

                        <MissionLogistiqueTabs context="finance" :active-tab="activeTab" />

                    </div>



                    <div v-if="flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">

                        {{ flash.success }}

                    </div>

                    <div v-if="flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">

                        {{ flash.error }}

                    </div>



                    <div :class="[missionTableWrap, '!mt-0 overflow-hidden']">

                        <MissionNumeroSearch :filtre-numero="filtreNumero" />

                        <table class="w-full min-w-[800px] text-sm">

                            <thead :class="missionTableHead">

                                <tr>

                                    <th :class="missionTableTh">N°</th>

                                    <th :class="missionTableTh">Bénéficiaire</th>

                                    <th :class="missionTableTh">Objet de la mission</th>

                                    <th :class="missionTableTh">Priorité</th>

                                    <th :class="missionTableTh">Dates prévues</th>

                                    <th :class="[missionTableTh, 'text-right']">Logistique validée</th>

                                    <th :class="missionTableTh">Statut</th>

                                    <th :class="missionTableTh">Étape</th>

                                    <th :class="missionTableThRight">Action</th>

                                </tr>

                            </thead>

                            <tbody>

                                <tr

                                    v-for="m in missions.data"

                                    :key="m.id"

                                    :class="missionTableRow"

                                >

                                    <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>

                                    <td class="p-3 font-medium text-neutral-900">

                                        {{ m.beneficiaire?.name || '—' }}

                                        <span class="block text-xs font-normal text-muted-foreground">

                                            Demandeur : {{ m.demandeur?.name }}

                                        </span>

                                    </td>

                                    <td class="p-3 text-neutral-700 max-w-xs truncate" :title="m.objet">

                                        {{ m.objet }}

                                    </td>

                                    <td class="p-3 capitalize" :class="priorityClass(m.priorite)">

                                        {{ m.priorite }}

                                    </td>

                                    <td class="p-3 text-neutral-600 tabular-nums">

                                        {{ formatPeriodeMission(m.date_debut, m.date_fin) }}

                                    </td>

                                    <td class="p-3 text-right font-bold text-neutral-900 tabular-nums">

                                        {{ formatMontant(m.total_logistique ?? 0) }}

                                    </td>

                                    <td class="p-3">

                                        <MissionValidationEtatBadge :etat="m.validation_etat" />

                                    </td>

                                    <td class="p-3 text-muted-foreground">{{ m.etape_libelle || '—' }}</td>

                                    <td class="p-3 text-right">

                                        <Button

                                            v-if="m.validation_etat === 'a_traiter'"

                                            size="sm"

                                            class="gap-2 bg-emerald-700 text-white hover:bg-emerald-800"

                                            as-child

                                        >

                                            <Link :href="`/missions/${m.id}`">

                                                <CheckCircle2 class="h-4 w-4" />

                                                Traiter

                                            </Link>

                                        </Button>

                                        <Button v-else variant="ghost" size="sm" as-child>

                                            <Link :href="`/missions/${m.id}`">

                                                <Eye class="mr-1 h-4 w-4" />

                                                Consulter

                                            </Link>

                                        </Button>

                                    </td>

                                </tr>

                                <tr v-if="!missions.data?.length">

                                    <td colspan="9" :class="missionEmptyState">

                                        Aucune mission en attente de validation Finance. Les missions déjà validées sont dans « Missions traitées / clôturées ».

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </MissionCard>

        </MissionPageShell>

    </AppLayout>

</template>

